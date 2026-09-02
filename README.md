# INTERFARM

**Sistema de gestión ganadera para el control integral de fincas de leche, carne y doble propósito.**

InterFarm es un software de gestión ganadera en la nube (SaaS) que permite administrar en un solo lugar la información de los animales, producción, reproducción, potreros, eventos y finanzas de una o varias fincas.

La plataforma está diseñada para facilitar el trabajo diario del ganadero mediante una aplicación instalable en el celular (PWA), permitiendo consultar y registrar información directamente desde la finca.

---

## Descripción

La gestión de una finca ganadera involucra información relacionada con animales, producción, reproducción, movimientos entre potreros, gastos, ingresos y diferentes actividades que deben ser controladas constantemente.

InterFarm centraliza esta información para evitar depender de cuadernos, planillas independientes o registros dispersos. Cada finca puede gestionarse de manera independiente y el propietario puede consultar un consolidado de la información de todas sus fincas.

El sistema permite registrar la información progresivamente y consultar el historial de cada animal, realizar seguimiento a la producción y reproducción, administrar potreros mediante mapas y obtener indicadores para apoyar la toma de decisiones.

---

## Objetivo

Proporcionar una herramienta digital que permita al ganadero **centralizar, organizar y consultar la información de su operación ganadera**, facilitando el seguimiento del hato y el análisis de aspectos productivos, reproductivos y financieros.

---

## Público objetivo

InterFarm está dirigido principalmente a:

* **Fincas lecheras:** seguimiento de la producción diaria de leche y lactancia.
* **Ganadería de carne:** control de pesos y producción cárnica.
* **Ganadería de doble propósito:** gestión conjunta de producción de leche y carne.
* **Propietarios de varias fincas:** administración de diferentes operaciones desde una sola cuenta.
* **Equipos de trabajo:** propietarios y empleados con diferentes roles y permisos dentro de una finca.

---

## Funcionalidades

### Gestión de cuentas y fincas

* Registro e inicio de sesión.
* Creación y configuración de fincas.
* Administración de múltiples fincas desde una misma cuenta.
* Cambio entre finca activa.
* Información independiente para cada finca.
* Vista consolidada de las fincas del propietario.
* Gestión de empleados.
* Asignación de roles y permisos.
* Administración del perfil de usuario.

### Gestión de animales

Cada animal cuenta con una ficha individual que permite registrar y consultar:

* Código interno.
* Arete.
* Nombre.
* Especie.
* Raza.
* Sexo.
* Categoría.
* Propósito.
* Fecha de nacimiento.
* Edad y etapa de desarrollo.
* Fotografías.
* Estado del animal.
* Ubicación.
* Notas.
* Registros de salud.

Los animales pueden clasificarse según su estado, por ejemplo:

* Activo.
* Vendido.
* Fallecido.

### Genealogía

InterFarm permite registrar las relaciones familiares de los animales y visualizar su descendencia mediante un árbol genealógico.

Incluye:

* Registro de madre y padre.
* Descendencia.
* Árbol genealógico visual.
* Identificación del estado de los animales dentro del árbol.

### Reproducción

El módulo reproductivo permite realizar seguimiento de los procesos reproductivos del hato.

Incluye:

* Registro de preñez.
* Semental utilizado.
* Fecha de preñez.
* Registro de partos.
* Número de partos.
* Fecha del último parto.
* Seguimiento reproductivo.
* Identificación de madres y sementales elegibles según edad y sexo.

### Lactancia y destete

Permite realizar seguimiento de la lactancia de cada animal y gestionar los procesos relacionados con el destete.

Incluye:

* Control de lactancia por animal.
* Registro de destete.
* Avisos de destete.
* Seguimiento de las crías.

### Lotes y potreros

InterFarm incorpora herramientas para representar y administrar los potreros de una finca.

Permite:

* Crear y editar lotes.
* Dibujar el croquis del potrero sobre un mapa satelital.
* Visualizar los lotes en el mapa.
* Asignar animales a un lote.
* Trasladar animales entre lotes.
* Consultar entradas y salidas.
* Mantener un historial de movimientos con fechas.
* Registrar observaciones de cada lote.

### Producción de leche

El sistema permite registrar la producción de leche de forma individual o general.

Se puede registrar:

* Producción por animal.
* Producción total diaria de la finca.
* Producción de la mañana.
* Producción de la tarde.
* Precio de la leche.
* Totales calculados automáticamente.

### Producción de carne

Permite realizar seguimiento de la producción cárnica mediante:

* Registro de pesos.
* Producción de carne.
* Precios configurables.

### Finanzas

El módulo financiero permite registrar los movimientos económicos asociados a las fincas.

Incluye:

* Registro de ingresos.
* Registro de gastos.
* Información financiera por finca.
* Consolidado de todas las fincas.
* Indicadores financieros.
* Gráfico de ingresos frente a gastos.
* Consulta por quincena.
* Consulta por mes.

### Reportes

InterFarm permite generar reportes relacionados con diferentes áreas de la operación ganadera.

Entre ellos:

* Reportes de lactancia.
* Reportes de preñez.
* Reportes de crías.
* Otros reportes de información registrada en el sistema.

Los reportes pueden exportarse en:

* CSV.
* Excel (`.xlsx`).

### Eventos

La plataforma incorpora una agenda para organizar las actividades relacionadas con el hato.

Permite registrar y consultar:

* Vacunas.
* Servicios.
* Partos.
* Actividades generales.
* Estado de los eventos.
* Línea de tiempo de actividades.

### Notificaciones

InterFarm utiliza notificaciones para informar al usuario sobre actividades y situaciones importantes.

Por ejemplo:

* Destetes.
* Pendientes.
* Eventos.
* Avisos del sistema.

Las notificaciones pueden recibirse mediante **notificaciones push web**, incluso cuando la aplicación no se encuentra abierta.

### Dashboard

El panel de control presenta una vista general de la información más importante de la operación.

Incluye:

* Indicadores clave.
* Total de leche del último día.
* Información general de las fincas.
* Mapa de las fincas.

---

## Aplicación móvil

InterFarm utiliza tecnología **Progressive Web App (PWA)**, por lo que puede instalarse directamente en el celular desde el navegador.

Una vez instalada, la plataforma puede utilizarse como una aplicación convencional desde la pantalla principal del dispositivo.

Esto permite que el ganadero pueda acceder al sistema desde diferentes lugares de la finca sin depender de un computador.

---

## Arquitectura del servicio

InterFarm funciona bajo un modelo **Software as a Service (SaaS)**.

La plataforma se encuentra alojada en la nube y funciona mediante cuentas de usuario y suscripciones.

El modelo contempla:

* Gestión de cuentas.
* Gestión de fincas.
* Facturación.
* Facturas en PDF.
* Formas de pago.
* Pagos integrados.
* Actualización continua del sistema.
* Almacenamiento de información en la nube.

Los precios y planes de suscripción corresponden a la definición comercial del servicio y no forman parte de la documentación funcional de la plataforma.

---

## Modelo de información

La información de InterFarm se organiza principalmente alrededor de la relación entre:

```text
Usuario
   │
   ├── Finca
   │     │
   │     ├── Animales
   │     │     ├── Genealogía
   │     │     ├── Reproducción
   │     │     ├── Lactancia
   │     │     └── Salud
   │     │
   │     ├── Lotes / Potreros
   │     │     └── Movimientos de animales
   │     │
   │     ├── Producción
   │     │     ├── Leche
   │     │     └── Carne
   │     │
   │     ├── Finanzas
   │     │     ├── Ingresos
   │     │     └── Gastos
   │     │
   │     ├── Eventos
   │     └── Reportes
   │
   └── Equipo y permisos
```

Esta estructura permite mantener los datos separados por finca y, al mismo tiempo, generar información consolidada para el propietario.

---

## Tecnologías y componentes

De acuerdo con la documentación de la plataforma, InterFarm incorpora diferentes tecnologías y componentes para ofrecer sus funcionalidades:

* **PWA:** instalación de la plataforma como aplicación.
* **Mapas:** visualización y creación de polígonos sobre mapas satelitales.
* **Leaflet:** interacción con mapas.
* **Esri World Imagery:** imágenes satelitales utilizadas para los croquis de los potreros.
* **CSV / Excel:** exportación de reportes.
* **Notificaciones Push Web:** envío de alertas al dispositivo.
* **SaaS:** modelo de distribución del software mediante suscripción.

---

## Flujo general de uso

El funcionamiento básico de InterFarm puede resumirse en tres etapas:

### 1. Crear la cuenta y la finca

El usuario se registra en la plataforma y configura una o varias fincas.

### 2. Registrar los animales

Se incorporan los animales al sistema con su información básica, identificación y fotografías.

### 3. Gestionar la operación

A partir de la información registrada, el usuario puede controlar:

* Producción.
* Reproducción.
* Lactancia.
* Potreros.
* Movimientos de animales.
* Eventos.
* Finanzas.
* Reportes.

---

## Beneficios

InterFarm busca facilitar la gestión cotidiana de la finca mediante:

* **Centralización de información:** los datos de la operación se encuentran en un mismo sistema.
* **Acceso desde el celular:** la información puede consultarse y registrarse directamente desde la finca.
* **Seguimiento individual:** cada animal cuenta con su propia información e historial.
* **Control productivo:** permite consultar la producción de leche y carne.
* **Seguimiento reproductivo:** facilita el control de preñeces, partos y crías.
* **Control de potreros:** permite conocer la ubicación y movimientos de los animales.
* **Gestión financiera:** facilita el seguimiento de ingresos y gastos.
* **Alertas:** ayuda a recordar actividades y fechas importantes.
* **Información para la toma de decisiones:** los indicadores y reportes permiten analizar la operación con los datos registrados.

---

## Roles y trabajo en equipo

InterFarm permite incorporar trabajadores a una finca y establecer diferentes niveles de acceso mediante **roles y permisos**.

El propietario puede:

* Invitar empleados.
* Asignar roles.
* Definir permisos.
* Retirar accesos.

Esto permite que cada integrante del equipo tenga acceso únicamente a las funciones necesarias para realizar su trabajo.

---

## Reportes y exportación

La información registrada en InterFarm puede convertirse en reportes para facilitar su consulta y análisis.

Los principales reportes contemplan información de:

* Lactancia.
* Preñez.
* Crías.
* Producción.
* Otros registros disponibles en la plataforma.

Los datos pueden exportarse a formatos **CSV** y **Excel (`.xlsx`)** para su posterior análisis o almacenamiento.

---

## Estado y alcance del producto

InterFarm contempla actualmente funcionalidades para la administración integral de una operación ganadera.

El alcance funcional documentado comprende:

1. Cuentas y fincas.
2. Animales.
3. Genealogía.
4. Reproducción.
5. Lactancia.
6. Lotes y potreros.
7. Producción de leche.
8. Producción de carne.
9. Finanzas.
10. Reportes.
11. Eventos.
12. Notificaciones.
13. Dashboard.
14. Aplicación instalable PWA.
15. Gestión de equipos, roles y permisos.
16. Servicio SaaS.

El panel de administración destinado a la gestión interna de planes, clientes y soporte es de uso administrativo y no forma parte de la aplicación destinada al usuario final.

---

## InterFarm en una frase

> **InterFarm es una plataforma de gestión ganadera en la nube que centraliza el control de animales, producción, reproducción, potreros y finanzas de una o varias fincas desde el celular.**

