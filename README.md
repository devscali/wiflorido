# 🐷 Wiflorido

Plugin de WordPress para administrar PDFs de promociones semanales. Perfecto para portales cautivos de WiFi.

**Desarrollado por [Cali Devs](https://calidevs.com)**

---

## 🚀 Instalación

1. Sube la carpeta `wiflorido` a `/wp-content/plugins/`
2. Activa el plugin en WordPress > Plugins
3. Ve a **Wiflorido 🐷** en el menú lateral
4. Sube tu primer PDF

## 📋 Características

- ✅ **Panel admin intuitivo** - Diseño limpio y fácil de usar
- ✅ **URL personalizable** - Configura el slug que quieras (`/playas`, `/tijuana`, etc.)
- ✅ **Visor de PDF responsive** - Se ve bien en móviles y desktop
- ✅ **Estadísticas de visitas** - Ve cuántas personas han visto las promociones
- ✅ **Página de "Próximamente"** - Se muestra automáticamente si no hay PDF
- ✅ **Validación de archivos** - Solo permite subir PDFs

## 🔧 Uso

### Para Marketing

1. Ir al menú **Wiflorido 🐷** en WordPress
2. Hacer clic en "Seleccionar PDF"
3. Elegir el PDF de promociones de la semana
4. ¡Listo! Ya está disponible en la URL

### Para el WiFi

Configura el portal cautivo del WiFi para redirigir a:
```
https://tusitio.com/playas
```

Los clientes verán las promociones automáticamente al conectarse.

## ⚙️ Configuración

### Cambiar el Slug

En la sección de Configuración puedes cambiar la URL:
- `/playas` → `tusitio.com/playas`
- `/tijuana` → `tusitio.com/tijuana`
- `/promo` → `tusitio.com/promo`

### Después de Instalar

Si la URL no funciona después de instalar:
1. Ve a **Ajustes > Enlaces permanentes**
2. Haz clic en **Guardar cambios** (sin modificar nada)
3. Esto recarga las reglas de WordPress

## 📱 Vista del Cliente

El visor incluye:
- Header con branding y cerdito animado 🐷
- PDF embebido en pantalla completa
- Botón de descarga
- Footer con créditos
- Spinner de carga mientras carga el PDF

## 🔄 Actualización Semanal

Cada semana solo hay que:
1. Ir a **Wiflorido 🐷**
2. Clic en "Reemplazar PDF"
3. Seleccionar el nuevo PDF
4. ¡Listo!

La URL no cambia, el WiFi sigue funcionando sin tocar nada.

## 📊 Estadísticas

El plugin cuenta automáticamente:
- Visitas totales a la página de promociones
- Fecha de última actualización
- Estado del PDF (activo/inactivo)

## 🛠️ Requisitos

- WordPress 5.0+
- PHP 7.4+

## 📞 Soporte

¿Problemas o sugerencias? Contacta a [Cali Devs](https://calidevs.com)

---

## 🗺️ Roadmap

### v1.1 - Múltiples Sucursales
- Administrar varias sucursales desde un solo panel
- Cada sucursal con su propia URL
- Dashboard con todas las sucursales

### v1.2 - Estadísticas Avanzadas
- Gráficas de visitas por día/semana
- Exportar reportes
- Integración con Google Analytics

---

**Hecho con ❤️ en Tijuana por [Cali Devs](https://calidevs.com)** 🐷
