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

# Get the latest Amazon Linux 2023 AMI
data "aws_ami" "amazon_linux" {
  most_recent = true
  owners      = ["amazon"]

  filter {
    name   = "name"
    values = ["al2023-ami-*-x86_64"]
  }

  filter {
    name   = "state"
    values = ["available"]
  }
}

# Create security group for Axialy Admin
resource "aws_security_group" "axialy_admin" {
  name        = "${var.instance_identifier}-sg"
  description = "Security group for Axialy Admin application"
  vpc_id      = data.aws_vpc.default.id

  ingress {
    from_port   = 22
    to_port     = 22
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
    description = "SSH access"
  }

  ingress {
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
    description = "HTTP access"
  }

  ingress {
    from_port   = 443
    to_port     = 443
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
    description = "HTTPS access"
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = {
    Name = "Axialy Admin Security Group"
  }
}

# Use template_file data source to avoid heredoc issues
data "template_file" "user_data" {
  template = file("${path.module}/../../scripts/user_data_template.sh")
  
  vars = {
    db_host              = var.db_host
    db_port              = var.db_port
    db_user              = var.db_user
    db_password          = var.db_password
    admin_default_user   = var.admin_default_user
    admin_default_email  = var.admin_default_email
    admin_default_password = var.admin_default_password
    smtp_host            = var.smtp_host
    smtp_port            = var.smtp_port
    smtp_user            = var.smtp_user
    smtp_password        = var.smtp_password
    smtp_secure          = var.smtp_secure
  }
}

# EC2 instance for Axialy Admin
resource "aws_instance" "axialy_admin" {
  ami           = data.aws_ami.amazon_linux.id
  instance_type = var.instance_type
  key_name      = var.key_pair_name

  vpc_security_group_ids = [aws_security_group.axialy_admin.id]
  subnet_id              = data.aws_subnets.default.ids[0]

  associate_public_ip_address = true

  user_data = base64encode(data.template_file.user_data.rendered)

  root_block_device {
    volume_type = "gp3"
    volume_size = 30
    encrypted   = true
  }

  tags = {
    Name        = var.instance_identifier
    Project     = "axialy-ai"
    Environment = "production"
    Component   = "admin"
  }

  lifecycle {
    create_before_destroy = true
  }
}

# Associate existing Elastic IP
data "aws_eip" "axialy_admin" {
  id = var.elastic_ip_allocation_id
}

resource "aws_eip_association" "axialy_admin" {
  instance_id   = aws_instance.axialy_admin.id
  allocation_id = data.aws_eip.axialy_admin.id

  depends_on = [aws_instance.axialy_admin]
}

# CloudWatch Log Group for application logs
resource "aws_cloudwatch_log_group" "axialy_admin" {
  name              = "/aws/ec2/${var.instance_identifier}"
  retention_in_days = 14

  tags = {
    Name = "Axialy Admin Logs"
  }
}
