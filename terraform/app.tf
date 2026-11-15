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

# Lab shortcut: lets any Azure service reach the server, including Container Apps.
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

# --- Container App ---

resource "azurerm_log_analytics_workspace" "dev" {
  name                = "log-platform-lab-dev"
  resource_group_name = azurerm_resource_group.dev.name
  location            = azurerm_resource_group.dev.location
  sku                 = "PerGB2018"
  retention_in_days   = 30
  tags                = local.tags
}

resource "azurerm_container_app_environment" "dev" {
  name                       = "cae-platform-lab-dev"
  resource_group_name        = azurerm_resource_group.dev.name
  location                   = azurerm_resource_group.dev.location
  log_analytics_workspace_id = azurerm_log_analytics_workspace.dev.id
  tags                       = local.tags
}

resource "azurerm_user_assigned_identity" "app" {
  name                = "id-bourbon"
  resource_group_name = azurerm_resource_group.dev.name
  location            = azurerm_resource_group.dev.location
  tags                = local.tags
}

resource "azurerm_role_assignment" "acr_pull" {
  scope                = data.azurerm_container_registry.shared.id
  role_definition_name = "AcrPull"
  principal_id         = azurerm_user_assigned_identity.app.principal_id
}

resource "azurerm_container_app" "bourbon" {
  name                         = "ca-bourbon"
  container_app_environment_id = azurerm_container_app_environment.dev.id
  resource_group_name          = azurerm_resource_group.dev.name
  revision_mode                = "Single"
  tags                         = local.tags

  identity {
    type         = "UserAssigned"
    identity_ids = [azurerm_user_assigned_identity.app.id]
  }

  registry {
    server   = data.azurerm_container_registry.shared.login_server
    identity = azurerm_user_assigned_identity.app.id
  }

  secret {
    name  = "db-password"
    value = random_password.db.result
  }

  ingress {
    external_enabled = true
    target_port      = 80
    traffic_weight {
      percentage      = 100
      latest_revision = true
    }
  }

  template {
    min_replicas = 0
    max_replicas = 1

    container {
      name   = "bourbon"
      image  = "${data.azurerm_container_registry.shared.login_server}/bourbon:latest"
      cpu    = 0.25
      memory = "0.5Gi"

      env {
        name  = "DB_HOST"
        value = azurerm_mysql_flexible_server.db.fqdn
      }
      env {
        name  = "DB_NAME"
        value = azurerm_mysql_flexible_database.bourbon.name
      }
      env {
        name  = "DB_USER"
        value = azurerm_mysql_flexible_server.db.administrator_login
      }
      env {
        name        = "DB_PASSWORD"
        secret_name = "db-password"
      }
    }
  }

  # The app pipeline owns which image is running; Terraform only sets the starting point.
  lifecycle {
    ignore_changes = [template[0].container[0].image]
  }

  depends_on = [azurerm_role_assignment.acr_pull]
}

output "app_url" {
  value = "https://${azurerm_container_app.bourbon.ingress[0].fqdn}"
}

output "db_fqdn" {
  value = azurerm_mysql_flexible_server.db.fqdn
}

output "db_password" {
  value     = random_password.db.result
  sensitive = true
}
