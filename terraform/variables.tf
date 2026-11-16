variable "location" {
  type    = string
  default = "westus2"
}

variable "readers_group_id" {
  type        = string
  description = "Object ID of the platform-readers Entra group"
}

variable "acr_name" {
  type        = string
  description = "Name of the long-lived container registry in the homelab_01 resource group"
}

variable "admin_ip" {
  type        = string
  default     = ""
  description = "Your public IP, so you can load data into MySQL. Leave empty to skip"
}

variable "aks_node_size" {
  type        = string
  default     = "Standard_B2ls_v2"
  description = "VM size of the single AKS node (2 vCPU / 4 GB). Standard_B2s_v2 doubles memory at double the price"
}

variable "dns_label" {
  type        = string
  default     = "barrelbook"
  description = "Public name of the app: https://<dns_label>.<location>.cloudapp.azure.com"
}

variable "cluster_admin_ids" {
  type        = list(string)
  default     = []
  description = "Entra object IDs (users or groups) given cluster-admin on AKS"
}


locals {
  tags = {
    owner   = "bobby"
    project = "homelab_01"
    env     = "dev"
  }
}
