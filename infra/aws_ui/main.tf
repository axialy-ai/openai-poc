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

# Get the latest STANDARD Amazon Linux 2023 AMI (NOT ECS Optimized)
data "aws_ami" "amazon_linux" {
  most_recent = true
  owners      = ["amazon"]

  filter {
    name   = "name"
    values = ["al2023-ami-2023*-x86_64"]
  }

  filter {
    name   = "state"
    values = ["available"]
  }

  filter {
    name   = "virtualization-type"
    values = ["hvm"]
  }

  # CRITICAL: Exclude ECS Optimized by filtering the description
  filter {
    name   = "description"
    values = ["Amazon Linux 2023 AMI 2023* x86_64 HVM kernel*"]
  }
}

# Create security group for Axialy UI
resource "aws_security_group" "axialy_ui" {
  name        = "${var.instance_identifier}-sg"
  description = "Security group for Axialy UI application"
  vpc_id      = data.aws_vpc.default.id

  # SSH access
  ingress {
    from_port   = 22
    to_port     = 22
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
    description = "SSH access"
  }

  # HTTP access
  ingress {
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
    description = "HTTP access"
  }

  # HTTPS access
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
    Name = "Axialy UI Security Group"
  }
}

# Minimal user data script for basic setup only
locals {
  user_data = base64encode(join("\n", [
    "#!/bin/bash",
    "set -e",
    "",
    "# Log everything",
    "exec > >(tee /var/log/user-data.log) 2>&1",
    "",
    "echo 'Starting basic system setup at $(date)'",
    "",
    "# Update system only",
    "echo 'Updating system packages...'",
    "dnf update -y",
    "",
    "# Install basic packages needed for SSH deployment",
    "echo 'Installing basic packages...'",
    "dnf install -y curl wget unzip",
    "",
    "# Create directory structure",
    "mkdir -p /var/www/html",
    "mkdir -p /var/log/axialy-ui",
    "",
    "# Install additional packages for UI application",
    "echo 'Installing Node.js and npm for potential frontend assets...'",
    "dnf install -y nodejs npm",
    "",
    "echo 'Basic setup completed at $(date)'"
  ]))
}

# EC2 instance for Axialy UI - using regular public IP, not Elastic IP
resource "aws_instance" "axialy_ui" {
  ami           = data.aws_ami.amazon_linux.id
  instance_type = var.instance_type
  key_name      = var.key_pair_name

  vpc_security_group_ids = [aws_security_group.axialy_ui.id]
  subnet_id              = data.aws_subnets.default.ids[0]

  associate_public_ip_address = true

  user_data = local.user_data

  root_block_device {
    volume_type = "gp3"
    volume_size = 40  # Increased for UI assets and potential media files
    encrypted   = true
  }

  tags = {
    Name        = var.instance_identifier
    Project     = "axialy-ai"
    Environment = "production"
    Component   = "ui"
  }

  lifecycle {
    create_before_destroy = true
  }
}

# CloudWatch Log Group for application logs
resource "aws_cloudwatch_log_group" "axialy_ui" {
  name              = "/aws/ec2/${var.instance_identifier}"
  retention_in_days = 14

  tags = {
    Name = "Axialy UI Logs"
  }
}
