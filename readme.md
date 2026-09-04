# YGB Sticky Header

**Contributors:** ygb  
**Tags:** sticky header, header, sticky, woocommerce, carrito  
**Requires at least:** 5.0  
**Tested up to:** 7.1  
**Requires PHP:** 7.4  
**Stable tag:** 1.8.0  
**License:** GPLv2 or later  
**License URI:** [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

Header sticky con prioridad baja para que el carrito emergente quede encima. Incluye throttle, altura dinámica y ocultamiento suave con transform.

## Description

YGB Sticky Header convierte el header de tu tema (compatible con temas que usan `.ast-header`, `.site-header` o `header#masthead`, como Astra y muchos otros) en un header fijo (sticky) que se oculta al hacer scroll hacia abajo y reaparece al hacer scroll hacia arriba.

### Características principales

* **Sticky inteligente**: El header se mantiene visible mientras haces scroll hacia arriba y se oculta automáticamente al bajar, mejorando la experiencia de lectura.
* **Prioridad de z-index baja**: Se asigna un `z-index: 9990` para que elementos como el carrito emergente de WooCommerce u otros popups queden por encima sin conflictos.
* **Rendimiento optimizado**: Implementa *throttle* en el evento de scroll (100ms) para reducir la carga en el navegador.
* **Ocultamiento completo con transform**: Usa `translateY(-100%)` en lugar de un desplazamiento fijo, garantizando que el header desaparezca por completo sin importar su altura.
* **Altura dinámica**: Recalcula la altura del header al redimensionar la ventana, evitando saltos visuales.
* **Soporte para barra de administración**: Ajusta automáticamente la posición cuando la barra de WordPress está visible (incluye responsive en móviles).
* **Compatible con PHP 8.2** y probado hasta WordPress 7.1.

## Installation

1. Sube la carpeta `ygb-sticky-header` al directorio `/wp-content/plugins/` o instálalo directamente desde el repositorio de WordPress.
2. Activa el plugin a través del menú "Plugins" en WordPress.
3. No requiere configuración adicional: el header de tu tema se volverá sticky automáticamente.

## Frequently Asked Questions

### ¿Funciona con cualquier tema?

Funciona con temas que utilicen las clases o IDs `.ast-header`, `.site-header` o `header#masthead`. La mayoría de temas populares (Astra, GeneratePress, Storefront, etc.) cumplen este requisito. Si tu tema usa otra estructura, puedes contactar al autor para solicitar compatibilidad.

### ¿Puedo ajustar la velocidad de ocultamiento?

Sí, el script incluye una distancia de scroll (`distancia = 100` píxeles) y un throttle de 100ms. Para personalizarlo, edita el archivo principal del plugin y modifica esos valores.

### ¿Por qué el header se oculta al hacer scroll hacia abajo?

Es un comportamiento intencional para maximizar el espacio de lectura. Si prefieres que siempre esté visible, elimina la clase `ygb-sticky-oculto` del script o ajusta la lógica en el archivo del plugin.

### ¿Interfiere con el carrito de WooCommerce?

No. El `z-index` bajo (9990) asegura que el carrito emergente y otros popups queden por encima del header sticky.

## Changelog

### 1.8.0
* Reemplazado `top: -200px` por `transform: translateY(-100%)` para ocultamiento completo del header.
* Simplificada la lógica de scroll eliminando condición redundante.
* Eliminado `register_uninstall_hook` en favor de `uninstall.php`.
* Actualizada compatibilidad a WordPress 7.1 y PHP 8.2.

### 1.7.0
* Añadido throttle para mejorar rendimiento en scroll.
* Recalculado dinámico de altura del header al redimensionar ventana.
* Soporte mejorado para barra de administración en móviles.
* Código optimizado y documentado.

### 1.0.0
* Versión inicial.

## Upgrade Notice

### 1.8.0
Actualización importante: mejora el ocultamiento del header usando transform, compatible con WordPress 7.1 y PHP 8.2. Se recomienda actualizar.