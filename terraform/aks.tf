# --- Kubernetes (AKS) ---
# One small node, no availability zones: this Free Trial subscription has a 4 vCPU regional
# quota (1 node + 1 temporary surge node during upgrades) and blocks zonal VMs in westus2.

data "azurerm_client_config" "current" {}

locals {
  # Azure gives the ingress load balancer's public IP this name (see k8s/platform/traefik-values.yaml).
  app_hostname = "${var.dns_label}.${azurerm_resource_group.dev.location}.cloudapp.azure.com"
}

# Control-plane identity, created first so it can be granted access to our subnet before the cluster exists.
resource "azurerm_user_assigned_identity" "aks" {
  name                = "id-aks-bourbon"
  resource_group_name = azurerm_resource_group.dev.name
  location            = azurerm_resource_group.dev.location
  tags                = local.tags
}

# AKS puts its nodes in snet-apps and manages load balancer settings there.
resource "azurerm_role_assignment" "aks_subnet" {
  scope                = azurerm_subnet.apps.id
  role_definition_name = "Network Contributor"
  principal_id         = azurerm_user_assigned_identity.aks.principal_id
}

resource "azurerm_kubernetes_cluster" "aks" {
  name                      = "aks-bourbon-dev"
  location                  = azurerm_resource_group.dev.location
  resource_group_name       = azurerm_resource_group.dev.name
  dns_prefix                = "aks-bourbon-dev"
  sku_tier                  = "Free"
  automatic_upgrade_channel = "patch"
  node_os_upgrade_channel   = "NodeImage"
  # Sign-in only with Entra ID; Kubernetes permissions come from Azure role assignments below.
  local_account_disabled = true
  tags                   = local.tags

  default_node_pool {
    name                        = "system"
    vm_size                     = var.aks_node_size
    node_count                  = 1
    os_disk_size_gb             = 30
    vnet_subnet_id              = azurerm_subnet.apps.id
    temporary_name_for_rotation = "systemtmp"

    upgrade_settings {
      max_surge = "1"
    }
  }

  identity {
    type         = "UserAssigned"
    identity_ids = [azurerm_user_assigned_identity.aks.id]
  }

  azure_active_directory_role_based_access_control {
    tenant_id          = data.azurerm_client_config.current.tenant_id
    azure_rbac_enabled = true
  }

  # Nodes get IPs from snet-apps; pods get IPs from a separate overlay range.
  network_profile {
    network_plugin      = "azure"
    network_plugin_mode = "overlay"
    pod_cidr            = "10.244.0.0/16" # trusted as the proxy range in app/docker/Dockerfile
    service_cidr        = "10.0.0.0/16"
    dns_service_ip      = "10.0.0.10"
    load_balancer_sku   = "standard"
  }

  depends_on = [azurerm_role_assignment.aks_subnet]
}

# Nodes pull app images from the shared registry.
resource "azurerm_role_assignment" "aks_acr_pull" {
  scope                            = data.azurerm_container_registry.shared.id
  role_definition_name             = "AcrPull"
  principal_id                     = azurerm_kubernetes_cluster.aks.kubelet_identity[0].object_id
  skip_service_principal_aad_check = true
}

# Cluster admin for whoever runs Terraform (the GitHub Actions identity), so the deploy job can apply manifests.
resource "azurerm_role_assignment" "aks_admin_ci" {
  scope                = azurerm_kubernetes_cluster.aks.id
  role_definition_name = "Azure Kubernetes Service RBAC Cluster Admin"
  principal_id         = data.azurerm_client_config.current.object_id
}

# Cluster admin for people (set via the CLUSTER_ADMIN_IDS GitHub variable).
resource "azurerm_role_assignment" "aks_admin" {
  for_each             = toset(var.cluster_admin_ids)
  scope                = azurerm_kubernetes_cluster.aks.id
  role_definition_name = "Azure Kubernetes Service RBAC Cluster Admin"
  principal_id         = each.value
}

output "aks_name" {
  value = azurerm_kubernetes_cluster.aks.name
}

output "aks_resource_group" {
  value = azurerm_kubernetes_cluster.aks.resource_group_name
}

output "dns_label" {
  value = var.dns_label
}

output "app_hostname" {
  value = local.app_hostname
}

output "app_url" {
  value = "https://${local.app_hostname}"
}
