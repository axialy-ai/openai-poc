22
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
    Name = "${var.instance_name} Security Group"
  }
}

# User data script for server setup
locals {
  user_data = base64encode(templatefile("${path.module}/user-data.sh", {
    db_host     = var.db_host
    db_port     = var.db_port
    db_user     = var.db_user
    db_password = var.db_password
    domain_name = var.domain_name
  }))
}

# EC2 instance for Axialy Admin
resource "aws_instance" "axialy_admin" {
  ami           = data.aws_ami.amazon_linux.id
  instance_type = var.instance_type
  key_name      = var.key_pair_name

  vpc_security_group_ids = [aws_security_group.axialy_admin.id]
  subnet_id              = data.aws_subnets.default.ids[0]

  associate_public_ip_address = true

  user_data = local.user_data

  root_block_device {
    volume_type = "gp3"
    volume_size = 20
    encrypted   = true
  }

  tags = {
    Name        = var.instance_name
    Project     = "axialy-ai"
    Environment = "production"
    Component   = "admin"
  }

  lifecycle {
    create_before_destroy = true
  }
}

# Elastic IP for static IP address
resource "aws_eip" "axialy_admin" {
  instance = aws_instance.axialy_admin.id
  domain   = "vpc"

  tags = {
    Name = "${var.instance_name} EIP"
  }

  depends_on = [aws_instance.axialy_admin]
}

# CloudWatch Log Group for application logs
resource "aws_cloudwatch_log_group" "axialy_admin_logs" {
  name              = "/aws/ec2/${var.instance_name}"
  retention_in_days = 14

  tags = {
    Name = "Axialy Admin Logs"
  }
}
