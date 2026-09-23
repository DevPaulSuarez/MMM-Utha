<?php
/* ============================================================
   MMM · Qué versión de la aplicación exige el servidor
   Lo lee api/version.php, que la app consulta cada vez que se abre.

   Al publicar una versión nueva de la app:
   1. Subir el número en app/MMM-Utha-App/pubspec.yaml y compilar
      con ./compilar.sh (ver DESPLIEGUE.md).
   2. Publicarla en la tienda y ESPERAR a que esté disponible.
   3. Recién entonces poner ese mismo número en 'minima' de aquí
      abajo y subirlo con ./desplegar.sh.

   El orden importa: si se sube el número antes de que la tienda
   apruebe la versión, todos quedan con la app detenida y sin nada
   nuevo que instalar.
   ============================================================ */

return [
    /* Versión mínima que se puede usar, igual que en pubspec.yaml
       pero sin el "+1" final: "1.2.0". Los celulares con una versión
       anterior se quedan en "Actualiza la aplicación" hasta que la
       instalen. Vacío ('') no obliga a nadie a actualizar. */
    'minima' => '1.0.0',

    /* Qué dice esa pantalla, por si conviene explicar el cambio
       ("Ahora las participaciones se responden desde la app").
       Vacío usa el texto de siempre. */
    'mensaje' => '',

    /* De dónde se baja la versión nueva. La app abre el que
       corresponde al celular; si el de su tienda está vacío, usa el
       'apk'. Con los tres vacíos la pantalla solo avisa.

       Todavía no está en las tiendas: por ahora todos van al APK, que
       sube ./publicar-apk.sh. Cuando entre a Google Play o a la App
       Store, se completa el enlace de la tienda y esa deja de usar el
       APK sola. */
    'enlaces' => [
        'android' => '',  // https://play.google.com/store/apps/details?id=com.mmm.utha
        'ios'     => '',  // https://apps.apple.com/app/id0000000000
        'apk'     => 'https://mmm.devpess.com/app/mmm-utha.apk',
    ],
];
