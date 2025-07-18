output "instance_id" {
  description = "ID of the EC2 instance"
  value       = aws_instance.axialy_ui.id
}

output "instance_ip" {
  description = "Public IP address of the EC2 instance"
  value       = aws_instance.axialy_ui.public_ip
}

output "instance_private_ip" {
  description = "Private IP address of the EC2 instance"
  value       = aws_instance.axialy_ui.private_ip
}

output "security_group_id" {
  description = "ID of the security group"
  value       = aws_security_group.axialy_ui.id
}

output "ui_url" {
  description = "URL to access the Axialy UI application"
  value       = var.domain_name != "" ? "http://${var.domain_name}/" : "http://${aws_instance.axialy_ui.public_ip}/"
}

output "ssh_command" {
  description = "SSH command to connect to the instance"
  value       = "ssh -i ~/.ssh/${var.key_pair_name}.pem ec2-user@${aws_instance.axialy_ui.public_ip}"
}

output "deployment_summary" {
  description = "Summary of the UI deployment"
  value = {
    instance_id     = aws_instance.axialy_ui.id
    public_ip       = aws_instance.axialy_ui.public_ip
    instance_type   = aws_instance.axialy_ui.instance_type
    ui_url          = var.domain_name != "" ? "http://${var.domain_name}/" : "http://${aws_instance.axialy_ui.public_ip}/"
    region          = var.aws_region
    database_host   = var.db_host
    smtp_configured = var.smtp_host != "" ? "Yes" : "No"
  }
}

output "estimated_monthly_cost" {
  description = "Estimated monthly cost breakdown"
  value = {
    ec2_instance = var.instance_type == "t3.small" ? "~$15-20 USD (t3.small)" : "~$25-40 USD (${var.instance_type})"
    storage_40gb = "~$4-5 USD (40GB gp3)"
    data_transfer = "~$2-5 USD"
    total_estimate = var.instance_type == "t3.small" ? "~$21-30 USD per month" : "~$31-50 USD per month"
    note = "No Elastic IP costs since using dynamic public IP"
  }
}
