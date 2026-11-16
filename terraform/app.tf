data "azurerm_container_registry" "shared" {
  name                = var.acr_name
  resource_group_name = "homelab_01"
}

resource "random_string" "suffix" {
  length  = 6
  special = false
  upper   = false
}

resource "random_password" "db" {
  length           = 24
  override_special = "-_#%"
}

# --- Database ---

resource "azurerm_mysql_flexible_server" "db" {
  name                   = "mysql-bourbon-${random_string.suffix.result}"
  resource_group_name    = azurerm_resource_group.dev.name
  location               = azurerm_resource_group.dev.location
  administrator_login    = "bourbonadmin"
  administrator_password = random_password.db.result
  sku_name               = "B_Standard_B1ms"
  version                = "8.0.21"
  backup_retention_days  = 1
  tags                   = local.tags

  storage {
    size_gb = 20
  }

  lifecycle {
    ignore_changes = [zone]
  }
}

resource "azurerm_mysql_flexible_database" "bourbon" {
  name                = "bourbon"
  resource_group_name = azurerm_resource_group.dev.name
  server_name         = azurerm_mysql_flexible_server.db.name
  charset             = "utf8mb4"
  collation           = "utf8mb4_unicode_ci"
}

# Lab shortcut: allows unencrypted connections, since the app isn't set up for TLS to MySQL.
resource "azurerm_mysql_flexible_server_configuration" "tls" {
  name                = "require_secure_transport"
  resource_group_name = azurerm_resource_group.dev.name
  server_name         = azurerm_mysql_flexible_server.db.name
  value               = "OFF"
}

# Lab shortcut: lets any Azure service reach the server, including the AKS cluster's outbound IP.
resource "azurerm_mysql_flexible_server_firewall_rule" "azure" {
  name                = "allow-azure-services"
  resource_group_name = azurerm_resource_group.dev.name
  server_name         = azurerm_mysql_flexible_server.db.name
  start_ip_address    = "0.0.0.0"
  end_ip_address      = "0.0.0.0"
}

resource "azurerm_mysql_flexible_server_firewall_rule" "admin" {
  count               = var.admin_ip == "" ? 0 : 1
  name                = "allow-admin"
  resource_group_name = azurerm_resource_group.dev.name
  server_name         = azurerm_mysql_flexible_server.db.name
  start_ip_address    = var.admin_ip
  end_ip_address      = var.admin_ip
}

# The app runs on AKS (aks.tf); the deploy workflow reads these outputs.

output "db_fqdn" {
  value = azurerm_mysql_flexible_server.db.fqdn
}

output "db_name" {
  value = azurerm_mysql_flexible_database.bourbon.name
}

output "db_user" {
  value = azurerm_mysql_flexible_server.db.administrator_login
}

output "db_password" {
  value     = random_password.db.result
  sensitive = true
}
