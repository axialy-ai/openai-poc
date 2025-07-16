data "aws_availability_zones" "available" {
  state = "available"
}

data "aws_vpc" "default" {
  default = true
}

data "aws_subnets" "default" {
  filter {
    name   = "vpc-id"
    values = [data.aws_vpc.default.id]
  }
}

resource "random_password" "db_password" {
  length  = 16
  special = true
}

resource "aws_db_parameter_group" "axialy_mysql" {
  family = "mysql8.0"
  name   = "${var.db_instance_identifier}-params"

  parameter {
    name         = "innodb_buffer_pool_size"
    value        = "134217728"
    apply_method = "pending-reboot"
  }

  parameter {
    name         = "max_connections"
    value        = "50"
    apply_method = "immediate"
  }

  parameter {
    name         = "innodb_log_file_size"
    value        = "67108864"
    apply_method = "pending-reboot"
  }

  parameter {
    name         = "slow_query_log"
    value        = "0"
    apply_method = "immediate"
  }

  parameter {
    name         = "general_log"
    value        = "0"
    apply_method = "immediate"
  }

  tags = {
    Name = "Axialy MySQL Parameters"
  }
}

resource "aws_db_subnet_group" "axialy" {
  name       = "${var.db_instance_identifier}-subnet-group"
  subnet_ids = data.aws_subnets.default.ids

  tags = {
    Name = "Axialy DB subnet group"
  }
}

resource "aws_security_group" "axialy_rds" {
  name        = "${var.db_instance_identifier}-rds-sg"
  description = "Security group for Axialy RDS instance"
  vpc_id      = data.aws_vpc.default.id

  # Allow MySQL/Aurora connections from anywhere (will be restricted during setup)
  ingress {
    from_port   = 3306
    to_port     = 3306
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
    description = "MySQL access from anywhere (temporary for setup)"
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Name = "Axialy RDS Security Group"
  }
}

resource "aws_db_instance" "axialy" {
  identifier     = var.db_instance_identifier
  engine         = "mysql"
  engine_version = "8.0.35"
  instance_class = var.db_instance_class
  
  allocated_storage     = var.allocated_storage
  max_allocated_storage = 0
  storage_type          = "gp2"
  storage_encrypted     = false
  
  db_name  = "axialy_main"
  username = "axialy_admin"
  password = random_password.db_password.result
  
  vpc_security_group_ids = [aws_security_group.axialy_rds.id]
  db_subnet_group_name   = aws_db_subnet_group.axialy.name
  parameter_group_name   = aws_db_parameter_group.axialy_mysql.name
  
  # Make it publicly accessible for initial setup
  publicly_accessible = true
  
  backup_retention_period = 1
  backup_window          = "03:00-04:00"
  maintenance_window     = "sun:04:00-sun:05:00"
  
  skip_final_snapshot = true
  deletion_protection = false
  
  performance_insights_enabled = false
  monitoring_interval         = 0
  
  enabled_cloudwatch_logs_exports = []
  
  apply_immediately = true
  
  tags = {
    Name        = "Axialy Database"
    Environment = "production"
    Project     = "axialy-ai"
  }
}

resource "aws_cloudwatch_log_group" "rds_error_log" {
  name              = "/aws/rds/instance/${var.db_instance_identifier}/error"
  retention_in_days = 1
  
  tags = {
    Name = "Axialy RDS Error Logs"
  }
}
