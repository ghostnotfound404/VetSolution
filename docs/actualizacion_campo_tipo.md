# Documentación de Actualización del Campo Tipo en VetSolution

## Descripción del Cambio

Se ha añadido un nuevo campo "Tipo" a los productos y servicios en VetSolution para especificar a qué área de negocio pertenecen (Clínica, Spa o PetShop). Este campo ahora es obligatorio y se utiliza automáticamente en las ventas, eliminando la necesidad de seleccionar manualmente el tipo durante el proceso de venta.

## Cambios Realizados

1. **Estructura de Base de Datos**:
   - Se añadió el campo `tipo` (VARCHAR 20) a la tabla `productos`
   - Se añadió el campo `tipo` (VARCHAR 20) a la tabla `servicios`
   - Se mantiene el campo `tipo_negocio` en la tabla `ventas` para compatibilidad

2. **Formularios**:
   - Se añadió un selector de tipo en el formulario de creación de productos
   - Se añadió un selector de tipo en el formulario de creación de servicios
   - Se eliminó el selector de tipo en el formulario de ventas

3. **Lógica de Negocio**:
   - El tipo ahora se obtiene automáticamente del producto o servicio seleccionado
   - El valor se almacena en la venta sin necesidad de que el usuario lo seleccione

## Valores de Tipo

Los valores posibles para el campo "tipo" son:
- `clinica` - Para productos y servicios relacionados con atención médica veterinaria
- `spa` - Para productos y servicios relacionados con peluquería y estética
- `petshop` - Para productos y accesorios de venta minorista

## Actualización de Instalación

El script de instalación (install.php) ha sido actualizado para incluir estos cambios en nuevas instalaciones. También incluye un mecanismo para actualizar instalaciones existentes añadiendo los nuevos campos a las tablas correspondientes.

Para actualizar una instalación existente:
1. Acceda a `http://su-servidor/VetSolution/install/install.php`
2. Seleccione la opción "Actualizar Estructura"

## Notas Importantes

- Todos los productos y servicios existentes tendrán el tipo "clinica" como valor predeterminado
- Es recomendable revisar y actualizar los productos y servicios existentes para asignarles el tipo correcto
