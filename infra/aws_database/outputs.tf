output "db_host" {
  description = "RDS hostname"
  value       = aws_db_instance.axialy.address
}

output "db_port" {
  description = "Port"
  value       = aws_db_instance.axialy.port
}

output "db_user" {
  description = "Admin user"
  value       = aws_db_instance.axialy.username
}

output "db_pass" {
  description = "Admin password"
  value       = random_password.admin.result
  sensitive   = true
}
