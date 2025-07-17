variable "aws_region" {
  description = "AWS region where resources will be created"
  type        = string
  default     = "us-west-2"
}

variable "instance_identifier" {
  description = "Identifier for the EC2 instance"
  type        = string
  default     = "axialy-admin"
  
  validation {
    condition     = can(regex("^[a-zA-Z][a-zA-Z0-9-]*$", var.instance_identifier))
    error_message = "Instance identifier must start with a letter and can only contain letters, numbers, and hyphens."
  }
}

variable "instance_type" {
  description = "EC2 instance type"
  type        = string
  default     = "t3.micro"
  
  validation {
    condition     = can(regex("^[a-z][0-9][a-z]?\\.[a-z]+$", var.instance_type))
    error_message = "Instance type must be a valid AWS EC2 instance type."
  }
}

variable "key_pair_name" {
  description = "Name of the AWS key pair for EC2 access"
  type        = string
}

variable "elastic_ip_allocation_id" {
  description = "Allocation ID of the Elastic IP to associate with the instance"
  type        = string
}

variable "domain_name" {
  description = "Domain name for the admin interface (optional)"
  type        = string
  default     = ""
}

# Database connection variables
variable "db_host" {
  description = "Database host (RDS endpoint)"
  type        = string
}

variable "db_port" {
  description = "Database port"
  type        = string
  default     = "3306"
}

variable "db_user" {
  description = "Database username"
  type        = string
}

variable "db_password" {
  description = "Database password"
  type        = string
  sensitive   = true
}

# Admin user configuration
variable "admin_default_user" {
  description = "Default admin username"
  type        = string
}

variable "admin_default_email" {
  description = "Default admin email"
  type        = string
}

variable "admin_default_password" {
  description = "Default admin password"
  type        = string
  sensitive   = true
}

# SMTP configuration
variable "smtp_host" {
  description = "SMTP server host"
  type        = string
}

variable "smtp_port" {
  description = "SMTP server port"
  type        = string
}

variable "smtp_user" {
  description = "SMTP username"
  type        = string
}

variable "smtp_password" {
  description = "SMTP password"
  type        = string
  sensitive   = true
}

variable "smtp_secure" {
  description = "SMTP secure connection setting"
  type        = string
}
