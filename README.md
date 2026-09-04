# YGB User Blocker

Bloqueo indefinido de usuarios por ID o email para WordPress. Impide el acceso al login normal y de WooCommerce, desconecta sesiones activas y redirige a la home. Incluye persistencia del email aunque la cuenta sea eliminada y mensajes personalizados globales o por usuario.

**Versión:** 1.0.0  
**Autor:** YGB  
**Licencia:** GPL-2.0+  
**Requiere WordPress:** 5.8+  
**Probado hasta:** 6.5  

---

## ✨ Características principales

- 🔒 Bloqueo por **email** o **ID de usuario**.
- 🧠 Persistencia del bloqueo aunque la cuenta sea eliminada.
- 🚫 Impide el acceso al **login normal** y al de **WooCommerce**.
- 🧹 Desconexión automática de sesiones activas.
- 🔀 Redirección a la home o mensaje de error configurable.
- 💬 **Mensajes personalizados** por usuario o globales.
- 📋 Página de administración para buscar, bloquear y desbloquear usuarios.
- 🧾 Registro opcional en trazas de YGB Escudo 2 (si está presente).
- ✅ Capability propia `ygb_ub_manage` asignada al rol administrador.

---

## 📦 Requisitos

- WordPress 5.8 o superior.
- PHP 7.4 o superior (recomendado 8.1+).
- MySQL 5.7+ o MariaDB 10.2+.
- WooCommerce (opcional para bloqueo en tienda).

---

## 🚀 Instalación

1. Descarga el plugin como archivo ZIP o clona este repositorio en `wp-content/plugins/ygb-user-blocker`.
2. Activa el plugin desde el menú **Plugins** en WordPress.
3. Automáticamente se creará la tabla `wp_ygb_ub_blocked_users` y la capability `ygb_ub_manage`.
4. Accede a **Usuarios → Usuarios Bloqueados** para gestionar los bloqueos.

---

## 🧭 Uso

### Bloquear usuarios

1. Ve a **Usuarios → Usuarios Bloqueados**.
2. Usa el campo de búsqueda para encontrar usuarios por email, nombre de usuario o nombre visible.
3. Selecciona uno o varios usuarios.
4. Escribe un motivo (obligatorio) y, opcionalmente, un mensaje personalizado.
5. Pulsa **Bloquear seleccionados**.

### Desbloquear usuarios

En la misma página, en la tabla de bloqueados, pulsa **Desbloquear** en la fila correspondiente.

### Configuración global

- Activa o desactiva el **mensaje personalizado al bloquear**.
- Define el **mensaje global** que verán los usuarios bloqueados.

---

## ⚙️ Hooks disponibles

### Acciones

- `ygb_ub_user_blocker_blocked` — Se ejecuta al bloquear un usuario. Parámetros: `(string $email, string $reason)`.
- `ygb_ub_user_blocker_unblocked` — Se ejecuta al desbloquear. Parámetros: `(string $email, int $user_id)`.

### Filtros

No se incluyen filtros públicos en esta versión.

---

## 🗂 Estructura del plugin

ygb-user-blocker/
├── ygb-user-blocker.php
├── uninstall.php
├── readme.md
├── readme.txt
├── includes/
│ ├── class-user-blocker.php
│ ├── class-user-blocker-install.php
│ └── admin/
│ └── class-admin-user-blocker.php


---

## ❓ Preguntas frecuentes

**¿El bloqueo persiste si elimino la cuenta del usuario?**  
Sí, el email queda registrado en la tabla personalizada y se mantiene el bloqueo.

**¿Puedo bloquear sin motivo?**  
No, el motivo es obligatorio para trazabilidad.

**¿Qué ocurre si desactivo el mensaje personalizado?**  
El login fallará silenciosamente y redirigirá a la home.

**¿Funciona con WooCommerce?**  
Sí, se integra con el login, checkout, carrito y página de mi cuenta.

---

## 🧪 Changelog

### 1.0.0
- Versión inicial independiente.
- Desacoplamiento de YGB Escudo 2.
- Prefijo propio `ygb_ub_` en tabla, opciones y meta keys.
- Capability `ygb_ub_manage`.
- Mejoras de sanitización y seguridad.

---

## 📄 Licencia

Este plugin está licenciado bajo GPL-2.0+. Puedes usarlo, modificarlo y distribuirlo libremente.

---

## 👥 Créditos

Desarrollado por el equipo de YGB como parte del ecosistema de seguridad YGB Escudo.
