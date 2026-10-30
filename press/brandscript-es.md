# LadyQR: BrandScript (español)

El mensaje central de LadyQR, siguiendo el marco StoryBrand (SB7, Donald Miller). El cliente es el héroe; LadyQR es el guía. La web, el README, el dossier de prensa y los textos del autor deben seguir esta historia, cada uno con su voz. Es la versión en español de `brandscript-en.md`, para el sitio del autor y para quien prefiera leerlo así.

## Una frase

> La mayoría de los generadores de QR gratuitos quieren tu texto, tu correo y, tarde o temprano, una suscripción. LadyQR hace el código en tu navegador, sin subir nada, y sigue siendo tuyo.

Variantes más cortas:

- «Códigos QR que no salen de tu navegador.» (el lema del README)
- «Escribe un enlace. Obtén un QR. Nada sale de tu navegador.»

## De dónde viene

LadyQR se hizo primero para los proyectos del propio autor, y después se compartió. Algunos de sus proyectos grandes con códigos QR dejaron de funcionar porque la empresa intermediaria cambió las normas, o porque el servicio simplemente dejó de funcionar. Un código impreso no se puede arreglar, y la lección fue: **no pongas el servidor de un desconocido entre tu texto y tu código.** La historia completa va en la web del autor (sección Proyectos). Aquí solo da empatía y autoridad al guía, en una o dos líneas, sin nombres ni fechas.

## 1. El personaje (el héroe)

Quien tiene que poner un QR en algo real, para imprimir o compartir: la carta de un restaurante, un cartel, una tarjeta, un evento, un aula, una asociación. Un segundo héroe, solo para el README y el dossier de prensa: el implementador web que lo instala para sí o para un cliente.

**Lo que quiere:** un código que funcione ahora y dentro de un año, sin entregar nada a cambio.

## 2. El problema

- **Villano:** el generador que se interpone entre tu texto y tu código.
- **Externo:** muchos generadores gratuitos piden cuenta o correo para algo trivial. Sus códigos «dinámicos» pasan por su servidor, con anuncios, rastreo y un plan que caduca.
- **Interno:** el miedo a imprimir dos mil folletos y ver que el código deja de funcionar. La sensación de que te vigilan por escribir un enlace.
- **Filosófico:** un QR es texto dibujado. No debería necesitar una cuenta, el servidor de otro ni una suscripción.

## 3. El guía (LadyQR)

- **Empatía:** «Solo querías un código QR.» Y: «Hemos estado ahí: códigos que imprimimos dejaron de funcionar porque la empresa intermedia cambió las normas.»
- **Autoridad:**
  - Privada por construcción: una política de seguridad estricta con `connect-src 'none'` hace que el navegador rechace cualquier conexión de la página. Cualquiera puede comprobarlo en las herramientas de desarrollo, en la pestaña Network.
  - Codificador propio, unas 450 líneas sin dependencias, verificado contra una implementación independiente en las 40 versiones y los cuatro niveles de corrección.
  - Funciona en un hosting compartido gratuito de 100 MB. **1qr.es** es la prueba (ver más abajo).
  - Libre y de código abierto (GPL-2.0-or-later), accesible y traducible.
  - Hecha por alguien que la necesitó primero.

## 4. El plan

**Plan de proceso** (dos puertas):

1. *Úsala:* escribe el enlace, mira cómo aparece el código, descarga un PNG o un SVG.
2. *Hazla tuya:* descarga el zip, súbelo por FTP, abre tu sitio y deja que el instalador haga el resto.

**Plan de acuerdo** (lo que quita el miedo a probarla): gratis, sin cuenta, sin anuncios, sin analítica, sin base de datos. Recuerda tus ajustes y nunca el texto que escribes. No te fíes de nuestra palabra: compruébalo.

## 5. Llamada a la acción

- **Directa:** Descarga la última versión.
- **Transitoria:** «¿Cómo sé que es privada?», la comprobación paso a paso, y el ejemplo de 1qr.es.

## 6. El fracaso (lo que está en juego)

- Un código impreso que muere cuando caduca la suscripción de otro o cambian sus normas.
- Tu texto en un servidor que no controlas.
- Un código que no se escanea, por poco contraste o colores invertidos.
- Dar tu correo por algo que debería ser un clic.

## 7. El éxito

- Un código en segundos, sin cuenta y sin rastro.
- Funciona mientras funcione el enlace que lleva dentro. Con el acortador, mientras funcione tu propio dominio.
- Enlaces cortos en tu dominio, un QR para cada uno, y todo verificable.

**Transformación (identidad):** de quien toma prestado un generador de QR a quien tiene el suyo.

## El papel de 1qr.es

La instancia del propio autor es el caso de éxito y la implementación de referencia: un dominio de cinco caracteres, un hosting compartido gratuito, enlaces cortos y códigos más pequeños gracias a `LADYQR_QR_BASE` en mayúsculas. Los textos lo enlazan como ejemplo, nunca como el producto. Solo el autor toca ese sitio.

## Cómo usarlo

- Habla primero de la persona y su problema, después de LadyQR.
- Cada texto responde rápido a tres preguntas: qué es, cómo me mejora la vida, cómo lo consigo.
- Una voz por pieza, la misma historia:
  - **Web** (inglés): el usuario final. Cercana y breve, con algún guiño. Es gratuita y libre, así que no suena a copy comercial.
  - **README** (inglés): técnico. Qué es, cómo funciona, cómo montarlo, desplegarlo, configurarlo y actualizarlo. La historia solo lo abre.
  - **Notas de prensa** (inglés): mezcla de los dos. El proyecto, para quién es, casos de uso, requisitos y una explicación técnica sencilla.
  - **Web del autor** (español, sección Proyectos): la historia personal, la motivación y lo aprendido.
- Mantén el tono del producto: claro y tranquilo, sin humo. El nombre trae el personaje: la mariquita. Que se note en pequeños guiños en los bordes (el saludo, la frase de cierre, la tarjeta final), uno o dos por pieza, nunca en el titular ni en el problema.

## Afirmaciones: seguras y con cuidado

Seguras, porque el repositorio las demuestra:

- La página no puede enviar a ninguna parte lo que escribes (CSP, comprobable).
- Sin cuenta, sin anuncios, sin analítica, sin base de datos.
- Libre y de código abierto, GPL-2.0-or-later.
- El codificador está verificado contra una implementación independiente.
- Funciona en un hosting compartido mínimo.

Con cuidado:

- **Navegadores.** Solo se ha probado en Chrome. No digas que funciona en todos.
- **«Nunca caduca».** Un código guarda el texto con el que se hizo, así que funciona mientras funcione ese enlace. Dilo así.
- **Otros generadores.** No los nombres nunca. Habla de «muchos generadores gratuitos» y de lo que vivió el autor.
- **Códigos pequeños con `LADYQR_QR_BASE`.** La mayoría de las cámaras los abren como dirección web, pero conviene probar un código antes de imprimirlo.
