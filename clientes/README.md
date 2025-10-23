# Configuración de clientes

Cada cliente debe tener su propia carpeta dentro de `clientes/` siguiendo la estructura:

```
clientes/
├── <slug>/
│   └── config.php
├── otro-cliente/
│   └── config.php
└── template.config.php
```

1. Copia `template.config.php` dentro de una carpeta nueva con el nombre (slug) del cliente.
2. Renombra el archivo copiado como `config.php`.
3. Rellena los valores según la infraestructura de base de datos y los elementos de marca del cliente.

El **slug** se detecta automáticamente mediante:
- El parámetro `client` en la petición (`GET`, `POST` o `REQUEST`).
- La cabecera `X-Client` o `X-Client-Slug`.
- El subdominio utilizado al acceder a la aplicación.

Si no se encuentra un slug válido o la configuración no existe, la API devolverá un error.
