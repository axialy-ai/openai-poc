variable "aws_region" {
  description = "AWS region"
  type        = string
  default     = "us-east-1"
}

variable "db_instance_identifier" {
  description = "DB instance identifier"
  type        = string
}

variable "db_instance_class" {
  description = "DB instance class"
  type        = string
  default     = "db.t4g.micro"
}

variable "allocated_storage" {
  description = "Initial allocated storage (GiB)"
  type        = number
  default     = 20
}

variable "enable_performance_insights" {
  description = "Toggle Performance Insights (adds ≈$9/mo)"
  type        = bool
  default     = false
}
