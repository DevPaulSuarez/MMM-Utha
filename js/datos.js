/* ============================================================
   MMM · Datos del sitio
   Edita SOLO este archivo para actualizar el contenido.
   Las rutas de imagen se escriben desde la raíz del proyecto;
   main.js les añade "../" cuando la página está en /paginas.
   ============================================================ */

/* Banners del slider del inicio.
   Por ahora se repite la misma imagen 3 veces; cuando tengas las
   definitivas solo cambia la ruta de cada "imagen". */
const BANNERS = [
  {
    imagen: "img/fondos/Lema_Familia_2026.png",
    alt: "Lema Familia 2026"
  },
  {
    imagen: "img/fondos/portada_mmm_2026_2.png",
    alt: "Lema Familia 2026"
  },
  {
    imagen: "img/fondos/portada_mmm_2026_3.jpg",
    alt: "Lema Familia 2026"
  }
];

/* Lemas por año. El texto y las imágenes vienen del sitio
   internacional; editá aquí para cambiarlos. */
const LEMAS = [
  {
    anio: "2026",
    titulo: "Familia",
    texto: "La familia es la base donde se forma y preserva la fe cristiana. En el hogar se establecen principios que sostienen a las generaciones y afirman la identidad espiritual. La familia transmite un mismo legado, manteniendo un núcleo firme e inalterable a lo largo del tiempo. Cuando los hogares se fortalecen, la obra de Dios se afirma y se proyecta hacia el futuro. \n\nGeneraciones se levantan cuando un hogar decide permanecer firme en la fe.",
    verso: "\"Yo y mi casa serviremos a Jehová. Josué 24:15\"",
    imagen: "https://mmmoficial.org/wp-content/uploads/2026/02/lema_familia_2026.jpg"
  },
  {
    anio: "2025",
    titulo: "Generaciones",
    texto: "Bajo el lema Generaciones, el Movimiento Misionero Mundial reafirma la Biblia como el legado eterno e inmutable que guía a la humanidad. Este enfoque busca transmitir las verdades de las Escrituras de padres a hijos, formando una iglesia sólida que permanezca firme ante los desafíos actuales y reconozca la Palabra de Dios como la luz que transforma vidas y revela el propósito divino.\n\n\n\nEl compromiso central es instruir a las nuevas generaciones en los principios bíblicos para que se levanten como testigos del poder de Dios. Al sembrar hoy en los niños y jóvenes, aseguramos que Su obra perdure en el tiempo.",
    verso: "\"Generación a generación celebrará tus obras, y anunciará tus poderosos hechos. Salmos 145:4\"",
    imagen: "https://mmmoficial.org/wp-content/uploads/2026/02/lema_mmm_generaciones_2025.jpg"
  },
  {
    anio: "2024",
    titulo: "Armonía",
    texto: "Dios, en su infinita sabiduría, nos enseña en uno de los versos más hermosos de la Biblia que la armonía trasciende el sentimiento del amor. Esta se define como el equilibrio de partes distintas para formar un todo, una unión que connota la belleza presente en la creación, como las notas graves y agudas que forman una melodía o las distintas piezas de un engranaje que, al ajustarse, logran un movimiento perfecto.\n\n\n\nEn este lema 2024, se nos revela que la verdadera armonía no surge solo de tener amor, sino de la capacidad de trabajar unidos a pesar de nuestras personalidades y cualidades distintas. Al integrarnos en un mismo propósito, logramos el equilibrio necesario para seguir moviendo la obra de Dios y reflejar Su gloria a través de la unidad del cuerpo de Cristo.",
    verso: "\"¡Mirad cuán bueno y cuán delicioso es habitar los hermanos juntos en armonía! Salmos 133:1\"",
    imagen: "https://mmmoficial.org/wp-content/uploads/2026/02/lema_mmm_armonia_2024.jpg"
  },
  {
    anio: "2023",
    titulo: "Testimonio",
    texto: "Han sido 60 años de un largo camino, de pruebas, obstáculos y lágrimas, pero que a través de ello fue el proceso para ver la gloria de Dios en una obra que se ha expandido por todo el mundo. Por eso, nos es grato presentar este lema que representa uno de los valores que ha caracterizado a esta obra en todos estos años: \\\"Testimonio\\\", valor que debe estar en todo creyente y que refleja la vida santa que ha decidido llevar, siendo un cristal transparente sin mancha de la cual reprochar.\n\n\n\nEl ícono es una vasija en llamas que representa la perfecta comunión del creyente que tiene una vida espiritual, pero asimismo todo está hecho de cristal, porque el testimonio es frágil y, si nos descuidamos, se puede perder todo. Valoremos el testimonio que Dios ha dado a su obra para seguir firmes en este camino en el que aún nos queda más por recorrer.",
    verso: "\"...Persevero... dando testimonio a pequeños y a grandes\". Hechos 26:22\"",
    imagen: "https://mmmoficial.org/wp-content/uploads/2026/02/lema_mmm_testimonio_2023.jpg"
  },
  {
    anio: "2022",
    titulo: "Disponibilidad",
    texto: "Servir al Señor es el mayor privilegio que uno puede tener en la vida, y hasta lo más sencillo que hagamos para Su obra tiene especial aprecio para Dios; pero no se puede servir con orgullo ni tampoco confiando en nuestras fuerzas y habilidades, sino humillando nuestro ser y reconociendo que sin Él nada somos, pues todo lo que llegamos a alcanzar y hacer viene por causa de Dios. Es allí, en la humillación, donde nace la disposición y las promesas de servir a un Dios del cual no merecemos Su gracia, pero que por amor nos da Sus bendiciones y al cual, agradecidos, levantamos la mano en señal de disposición para servirle y hacer Su voluntad hasta el final.",
    verso: "\"Después oí la voz del Señor, que decía: \"¿A quién enviaré, y quién irá por nosotros?\". Entonces respondí yo: \"Heme aquí, envíame a mí\" Isaías 6:8\"",
    imagen: "https://mmmoficial.org/wp-content/uploads/2026/02/lema_mmm_disponibiildad_2022.jpg"
  },
  {
    anio: "2021",
    titulo: "Estabilidad",
    texto: "En aquellos días de los reyes y los caballeros eran constantes las guerras; la necesidad de todo reino era tener una fortificación segura que resistiera los asedios, diera seguridad al pueblo y tuviera ventaja sobre sus enemigos. Por esta razón se construían los castillos, que se ubicaban en los lugares altos sobre una base estable, ya que era muy importante su posición, haciendo difícil al enemigo atacar, siendo este edificio el que debía resistir más cuando las murallas eran rotas. Nuestra vida espiritual es como un castillo que necesita estar cimentado en Cristo y Su Palabra, lo cual nos permitirá resistir los numerosos ataques del enemigo en esta guerra espiritual. Se viven tiempos muy difíciles y es necesario estar en el fundamento, que es Cristo y la doctrina que nos fue enseñada, dándonos la estabilidad en medio de este mundo donde peregrinamos.",
    verso: "\"Por tanto, Jehová el Señor dice así: He aquí que yo he puesto en Sion por fundamento una piedra, piedra probada, angular, preciosa, de cimiento estable; el que creyere, no se apresure\". Isaías 28:16\"",
    imagen: "https://mmmoficial.org/wp-content/uploads/2026/02/lema_mmm_estabilidad_2021.jpg"
  },
  {
    anio: "2020",
    titulo: "Kyrios",
    texto: "Cuando Jesús resucitó, fue recibido en gloria y exaltado por Dios, quien le otorgó un nombre que es sobre todo nombre para que toda lengua confiese que Él es el Señor (Kýrios). Jesús es la máxima autoridad en el universo y el Soberano de toda Su creación; una verdad que los primeros cristianos reconocieron con amor y sumisión, estando dispuestos a padecer vituperios, persecución y hasta la muerte por causa de Su nombre.\n\n\n\nHoy reafirmamos que Jesucristo sigue siendo el Amo y Señor de nuestras vidas, pues Él es quien gobierna y es el único dueño de esta obra misionera. Al rendir nuestra voluntad ante Su soberanía, permitimos que Su autoridad guíe cada paso del camino, reconociendo que solo bajo Su señorío la iglesia permanece firme y cumple su propósito eterno en la tierra.",
    verso: "\"Por lo cual Dios también le exaltó hasta lo sumo, y le dio un nombre que es sobre todo nombre, para que en el nombre de Jesús se doble toda rodilla de los que están en los cielos, y en la tierra, y debajo de la tierra\" Filipenses 2:9-10\"",
    imagen: "https://mmmoficial.org/wp-content/uploads/2026/02/lema_mmm_kyrios_2020.jpg"
  },
  {
    anio: "2019",
    titulo: "Shekinah",
    texto: "Este lema nos declara que Dios habita en medio de Su pueblo, manifestándose como ese fuego que arde insistentemente y nos acompaña en cada instante del camino. Es Su presencia la que nos fortalece para seguir caminando firmes y constantes en las sendas del Evangelio, reconociendo que la guía del Espíritu Santo es el fundamento que sostiene nuestra fe ante cualquier adversidad.\n\n\n\nEste año será un tiempo de gloria marcado por las manifestaciones de Dios y un avivamiento profundo del Espíritu Santo en cada corazón. Al mantener encendida la llama de Su presencia, aseguramos que la obra siga avanzando con poder, permitiendo que Su fuego purificador transforme vidas y cumpla el propósito divino en toda la congregación.",
    verso: "\"Porque donde están dos o tres congregados en mi nombre, allí estoy yo en medio de ellos\". Mateo 18:20\"",
    imagen: "https://mmmoficial.org/wp-content/uploads/2026/02/lema_mmm_shekina_2019.jpg"
  },
  {
    anio: "2018",
    titulo: "Credibilidad",
    texto: "Vivimos en tiempos de gran desconfianza donde la mayoría de las organizaciones e instituciones públicas y privadas han perdido la credibilidad, convirtiéndose en una crisis para los ciudadanos. Frente a esta inestabilidad social les presentamos el nuevo lema para el 2018: \\\"CREDIBILIDAD, cualidad que inspira confianza (1 Pedro 2:12)\\\". Lema que nos dirige a la seguridad, bienestar y utilidad que se refleja. La persona creíble a través de los hechos embellecen su discurso\\\"",
    verso: "\"Manteniendo buena vuestra manera de vivir entre los gentiles; para que en lo que murmuran de vosotros como de malhechores, glorifiquen a Dios en el día de la visitación, al considerar vuestras buenas obras\". 1 Pedro 2:12\"",
    imagen: "https://mmmoficial.org/wp-content/uploads/2026/02/lema_mmm_credibilidad_2018.jpg"
  },
  {
    anio: "2017",
    titulo: "Responsabilidad",
    texto: "Este año, el Señor Jesucristo entregó para la obra del Movimiento Misionero Mundial la siguiente consigna: \\\"Responsabilidad 2017, cualidad que no admite excusas - Romanos cap.14: vers.12\\\", que orienta al cumplimiento de las obligaciones, a asumir compromisos y sus consecuencias, al no vivir del \\\"no puedo\\\" porque Dios dijo en su Palabra: \\\"Todo lo puedo en Cristo que me fortalece\\\", Filipenses 4:13.",
    verso: "\"De manera que cada uno de nosotros dará a Dios cuenta de sí\". Romanos 14:12\"",
    imagen: "https://mmmoficial.org/wp-content/uploads/2026/02/lema_mmm_responsabilidad_2017.jpg"
  }
];

const REPRESENTANTES = [
  {
    nombre: "Nombre Apellido",
    cargo: "Presidente",
    descripcion: "Breve reseña del representante.",
    foto: "img/representantes/representante-01.jpg"
  },
  {
    nombre: "Nombre Apellido",
    cargo: "Secretario/a",
    descripcion: "Breve reseña del representante.",
    foto: "img/representantes/representante-02.jpg"
  },
  {
    nombre: "Nombre Apellido",
    cargo: "Tesorero/a",
    descripcion: "Breve reseña del representante.",
    foto: "img/representantes/representante-03.jpg"
  }
];

const HORARIOS = [
  { dia: "Miércoles", hora: "7:00 - 8:00 p. m.",  actividad: "Culto de oración",   lugar: "Templo" },
  { dia: "Viernes",   hora: "7:00 - 9:00 p. m.",  actividad: "Culto general",      lugar: "Templo" },
  { dia: "Domingo",   hora: "10:00 a. m. - 1:00 p. m.", actividad: "Escuela dominical", lugar: "Templo" },
  { dia: "Domingo",   hora: "3:00 - 6:00 p. m.",  actividad: "Culto de la tarde",  lugar: "Templo" }
];

/* La misma agenda vista como semana, para el almanaque del inicio.
   Se repite igual todas las semanas. */
const AGENDA = [
  { dia: "Lunes",     eventos: [] },
  { dia: "Martes",    eventos: [] },
  {
    dia: "Miércoles",
    eventos: [{ nombre: "Culto de oración", hora: "7:00 - 8:00 p. m." }]
  },
  { dia: "Jueves",    eventos: [] },
  {
    dia: "Viernes",
    eventos: [{ nombre: "Culto general", hora: "7:00 - 9:00 p. m." }]
  },
  { dia: "Sábado",    eventos: [] },
  {
    dia: "Domingo",
    eventos: [
      { nombre: "Escuela dominical", hora: "10:00 a. m. - 1:00 p. m." },
      { nombre: "Culto de la tarde", hora: "3:00 - 6:00 p. m." }
    ]
  }
];

const NOTICIAS = [
  {
    titulo: "Título de la noticia 1",
    fecha: "2026-02-15",
    resumen: "Resumen breve de la noticia para mostrar en la tarjeta.",
    imagen: "img/noticias/noticia-01.jpg"
  },
  {
    titulo: "Título de la noticia 2",
    fecha: "2026-02-02",
    resumen: "Resumen breve de la noticia para mostrar en la tarjeta.",
    imagen: "img/noticias/noticia-02.jpg"
  },
  {
    titulo: "Título de la noticia 3",
    fecha: "2026-01-20",
    resumen: "Resumen breve de la noticia para mostrar en la tarjeta.",
    imagen: "img/noticias/noticia-03.jpg"
  }
];
