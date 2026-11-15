variable "location" {
  type    = string
  default = "eastus2"
}

variable "readers_group_id" {
  type        = string
  description = "Object ID of the platform-readers Entra group"
}

variable "acr_name" {
  type        = string
  description = "Name of the long-lived container registry in rg-platform-lab"
}

variable "admin_ip" {
  type        = string
  default     = ""
  description = "Your public IP, so you can load data into MySQL. Leave empty to skip"
}


locals {
  tags = {
    owner   = "bobby"
    project = "homelab_01"
    env     = "dev"
  }
}
