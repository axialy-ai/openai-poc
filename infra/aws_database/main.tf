########################################
# Look‑ups
########################################
data "aws_vpc" "default"   { default = true }
data "aws_subnets" "all"   {
  filter { name = "vpc-id" values = [data.aws_vpc.default.id] }
}

########################################
# Helpers & secrets
########################################
resource "random_password" "admin" {
  length           = 20
  special          = true
  override_special = "!#$%&*()-_=+[]{}<>:?"
}

########################################
# Networking – open to the world *for PoC*
########################################
resource "aws_security_group" "rds" {
  name        = "${var.db_instance_identifier}-sg"
  description = "Axialy PoC – open MySQL"
  vpc_id      = data.aws_vpc.default.id

  ingress {
    from_port   = 3306
    to_port     = 3306
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }
}

########################################
# RDS parameter & subnet groups
########################################
resource "aws_db_parameter_group" "params" {
  name   = "${var.db_instance_identifier}-params"
  family = "mysql8.0"

  parameter { name = "max_connections"        value = "100" }
  parameter { name = "innodb_buffer_pool_size" value = "{DBInstanceClassMemory*3/4}" }
  parameter { name = "slow_query_log"          value = "1" }
  parameter { name = "long_query_time"         value = "2" }
}

resource "aws_db_subnet_group" "subnets" {
  name       = "${var.db_instance_identifier}-subnet"
  subnet_ids = data.aws_subnets.all.ids
}

########################################
# The RDS instance (cheap!)
########################################
resource "aws_db_instance" "axialy" {
  identifier                = var.db_instance_identifier
  engine                    = "mysql"
  engine_version            = "8.0.35"
  instance_class            = var.db_instance_class

  allocated_storage         = var.allocated_storage
  max_allocated_storage     = var.allocated_storage * 2
  storage_type              = "gp3"
  storage_encrypted         = true

  db_name                   = "axialy"
  username                  = "axialy_admin"
  password                  = random_password.admin.result

  vpc_security_group_ids    = [aws_security_group.rds.id]
  db_subnet_group_name      = aws_db_subnet_group.subnets.name
  parameter_group_name      = aws_db_parameter_group.params.name

  backup_retention_period   = 7
  skip_final_snapshot       = true
  deletion_protection       = false

  performance_insights_enabled = var.enable_performance_insights

  enabled_cloudwatch_logs_exports = ["error", "general", "slowquery"]

  tags = { Name = "Axialy‑MySQL" }
}

########################################
# Log groups (cost ≈ $0)
########################################
resource "aws_cloudwatch_log_group" "error"    { name = "/aws/rds/instance/${var.db_instance_identifier}/error"    retention_in_days = 7 }
resource "aws_cloudwatch_log_group" "general"  { name = "/aws/rds/instance/${var.db_instance_identifier}/general"  retention_in_days = 7 }
resource "aws_cloudwatch_log_group" "slow"     { name = "/aws/rds/instance/${var.db_instance_identifier}/slowquery" retention_in_days = 7 }
