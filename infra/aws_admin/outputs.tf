output "instance_id" {
  description = "ID of the EC2 instance"
  value       = aws_instance.axialy_admin.id
}

output "instance_ip" {
  description = "Public IP address of the EC2 instance"
  value       = data.aws_eip.axialy_admin.public_ip
}

output "instance_private_ip" {
  description = "Private IP address of the EC2 instance"
  value       = aws_instance.axialy_admin.private_ip
}

output "security_group_id" {
  description = "ID of the security group"
  value       = aws_security_group.axialy_admin.id
}

output "admin_url" {
  description = "URL to access the Axialy Admin interface"
  value       = var.domain_name != "" ? "http://${var.domain_name}/admin_login.php" : "http://${data.aws_eip.axialy_admin.public_ip}/admin_login.php"
}

output "ssh_command" {
  description = "SSH command to connect to the instance"
  value       = "ssh -i ~/.ssh/${var.key_pair_name}.pem ec2-user@${data.aws_eip.axialy_admin.public_ip}"
}

output "deployment_summary" {
  description = "Summary of the deployment"
  value = {
    instance_id     = aws_instance.axialy_admin.id
    public_ip       = data.aws_eip.axialy_admin.public_ip
    instance_type   = aws_instance.axialy_admin.instance_type
    admin_url       = var.domain_name != "" ? "http://${var.domain_name}/admin_login.php" : "http://${data.aws_eip.axialy_admin.public_ip}/admin_login.php"
    region          = var.aws_region
    database_host   = var.db_host
  }
}

output "estimated_monthly_cost" {
  description = "Estimated monthly cost breakdown"
  value = {
    ec2_instance = var.instance_type == "t3.micro" ? "~$8-10 USD (t3.micro)" : "~$15-25 USD (${var.instance_type})"
    elastic_ip   = "~$3-5 USD (when instance stopped)"
    data_transfer = "~$1-3 USD"
    total_estimate = var.instance_type == "t3.micro" ? "~$12-18 USD per month" : "~$19-33 USD per month"
  }
}
