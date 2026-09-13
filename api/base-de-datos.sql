-- ============================================================
-- MMM · Base de datos
-- Importar una sola vez en una base vacía (phpMyAdmin > Importar).
-- Crea las tablas y las llena con el contenido que tenía
-- js/datos.json. Los usuarios se agregan con crear-usuario.php.
-- ============================================================

SET NAMES utf8mb4;

-- --- Acceso de la aplicación --------------------------------

CREATE TABLE usuarios (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario    VARCHAR(50)  NOT NULL UNIQUE,
  clave_hash VARCHAR(255) NOT NULL,
  nombre     VARCHAR(100) NOT NULL DEFAULT '',
  activo     TINYINT(1)   NOT NULL DEFAULT 1,
  creado     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Solo se guarda el hash sha256 del token, nunca el token
CREATE TABLE tokens (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64)     NOT NULL UNIQUE,
  vence      DATETIME     NOT NULL,
  creado     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (vence),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE intentos_login (
  id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip    VARCHAR(45) NOT NULL,
  fecha DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (ip, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Contenido del sitio ------------------------------------

-- Un único registro, id = 1
CREATE TABLE ubicacion (
  id          TINYINT UNSIGNED PRIMARY KEY,
  direccion   VARCHAR(255) NOT NULL DEFAULT '',
  coordenadas VARCHAR(100) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE banners (
  id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  imagen VARCHAR(500) NOT NULL,
  alt    VARCHAR(255) NOT NULL DEFAULT '',
  orden  INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lemas (
  id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  anio   CHAR(4)      NOT NULL,
  titulo VARCHAR(150) NOT NULL,
  texto  TEXT         NOT NULL,
  verso  VARCHAR(255) NOT NULL DEFAULT '',
  imagen VARCHAR(500) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE representantes (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(150) NOT NULL,
  cargo       VARCHAR(150) NOT NULL DEFAULT '',
  descripcion TEXT         NOT NULL,
  foto        VARCHAR(500) NOT NULL DEFAULT '',
  orden       INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE horarios (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dia       VARCHAR(30)  NOT NULL,
  hora      VARCHAR(50)  NOT NULL,
  actividad VARCHAR(150) NOT NULL,
  lugar     VARCHAR(150) NOT NULL DEFAULT '',
  orden     INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- dia: 1 = lunes … 7 = domingo
CREATE TABLE agenda_eventos (
  id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dia    TINYINT UNSIGNED NOT NULL,
  nombre VARCHAR(150)     NOT NULL,
  hora   VARCHAR(50)      NOT NULL,
  orden  INT              NOT NULL DEFAULT 0,
  INDEX (dia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE noticias (
  id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titulo  VARCHAR(200) NOT NULL,
  fecha   DATE         NOT NULL,
  resumen TEXT         NOT NULL,
  imagen  VARCHAR(500) NOT NULL DEFAULT '',
  INDEX (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Contenido inicial (copiado de js/datos.json) -----------

INSERT INTO ubicacion (id, direccion, coordenadas) VALUES
  (1, '2262 W 5400 S, Salt Lake City, UT 84129', '');

INSERT INTO banners (imagen, alt, orden) VALUES
  ('img/fondos/Lema_Familia_2026.webp', 'Lema Familia 2026', 1),
  ('img/fondos/portada_mmm_2026_2.webp', 'Lema Familia 2026', 2),
  ('img/fondos/portada_mmm_2026_3.webp', 'Lema Familia 2026', 3);

INSERT INTO lemas (anio, titulo, texto, verso, imagen) VALUES
  ('2026', 'Familia', 'La familia es la base donde se forma y preserva la fe cristiana. En el hogar se establecen principios que sostienen a las generaciones y afirman la identidad espiritual. La familia transmite un mismo legado, manteniendo un núcleo firme e inalterable a lo largo del tiempo. Cuando los hogares se fortalecen, la obra de Dios se afirma y se proyecta hacia el futuro. 

Generaciones se levantan cuando un hogar decide permanecer firme en la fe.', '"Yo y mi casa serviremos a Jehová. Josué 24:15"', 'https://mmmoficial.org/wp-content/uploads/2026/02/lema_familia_2026.jpg'),
  ('2025', 'Generaciones', 'Bajo el lema Generaciones, el Movimiento Misionero Mundial reafirma la Biblia como el legado eterno e inmutable que guía a la humanidad. Este enfoque busca transmitir las verdades de las Escrituras de padres a hijos, formando una iglesia sólida que permanezca firme ante los desafíos actuales y reconozca la Palabra de Dios como la luz que transforma vidas y revela el propósito divino.



El compromiso central es instruir a las nuevas generaciones en los principios bíblicos para que se levanten como testigos del poder de Dios. Al sembrar hoy en los niños y jóvenes, aseguramos que Su obra perdure en el tiempo.', '"Generación a generación celebrará tus obras, y anunciará tus poderosos hechos. Salmos 145:4"', 'https://mmmoficial.org/wp-content/uploads/2026/02/lema_mmm_generaciones_2025.jpg'),
  ('2024', 'Armonía', 'Dios, en su infinita sabiduría, nos enseña en uno de los versos más hermosos de la Biblia que la armonía trasciende el sentimiento del amor. Esta se define como el equilibrio de partes distintas para formar un todo, una unión que connota la belleza presente en la creación, como las notas graves y agudas que forman una melodía o las distintas piezas de un engranaje que, al ajustarse, logran un movimiento perfecto.



En este lema 2024, se nos revela que la verdadera armonía no surge solo de tener amor, sino de la capacidad de trabajar unidos a pesar de nuestras personalidades y cualidades distintas. Al integrarnos en un mismo propósito, logramos el equilibrio necesario para seguir moviendo la obra de Dios y reflejar Su gloria a través de la unidad del cuerpo de Cristo.', '"¡Mirad cuán bueno y cuán delicioso es habitar los hermanos juntos en armonía! Salmos 133:1"', 'https://mmmoficial.org/wp-content/uploads/2026/02/lema_mmm_armonia_2024.jpg'),
  ('2023', 'Testimonio', 'Han sido 60 años de un largo camino, de pruebas, obstáculos y lágrimas, pero que a través de ello fue el proceso para ver la gloria de Dios en una obra que se ha expandido por todo el mundo. Por eso, nos es grato presentar este lema que representa uno de los valores que ha caracterizado a esta obra en todos estos años: \\"Testimonio\\", valor que debe estar en todo creyente y que refleja la vida santa que ha decidido llevar, siendo un cristal transparente sin mancha de la cual reprochar.



El ícono es una vasija en llamas que representa la perfecta comunión del creyente que tiene una vida espiritual, pero asimismo todo está hecho de cristal, porque el testimonio es frágil y, si nos descuidamos, se puede perder todo. Valoremos el testimonio que Dios ha dado a su obra para seguir firmes en este camino en el que aún nos queda más por recorrer.', '"...Persevero... dando testimonio a pequeños y a grandes". Hechos 26:22"', 'https://mmmoficial.org/wp-content/uploads/2026/02/lema_mmm_testimonio_2023.jpg'),
  ('2022', 'Disponibilidad', 'Servir al Señor es el mayor privilegio que uno puede tener en la vida, y hasta lo más sencillo que hagamos para Su obra tiene especial aprecio para Dios; pero no se puede servir con orgullo ni tampoco confiando en nuestras fuerzas y habilidades, sino humillando nuestro ser y reconociendo que sin Él nada somos, pues todo lo que llegamos a alcanzar y hacer viene por causa de Dios. Es allí, en la humillación, donde nace la disposición y las promesas de servir a un Dios del cual no merecemos Su gracia, pero que por amor nos da Sus bendiciones y al cual, agradecidos, levantamos la mano en señal de disposición para servirle y hacer Su voluntad hasta el final.', '"Después oí la voz del Señor, que decía: "¿A quién enviaré, y quién irá por nosotros?". Entonces respondí yo: "Heme aquí, envíame a mí" Isaías 6:8"', 'https://mmmoficial.org/wp-content/uploads/2026/02/lema_mmm_disponibiildad_2022.jpg'),
  ('2021', 'Estabilidad', 'En aquellos días de los reyes y los caballeros eran constantes las guerras; la necesidad de todo reino era tener una fortificación segura que resistiera los asedios, diera seguridad al pueblo y tuviera ventaja sobre sus enemigos. Por esta razón se construían los castillos, que se ubicaban en los lugares altos sobre una base estable, ya que era muy importante su posición, haciendo difícil al enemigo atacar, siendo este edificio el que debía resistir más cuando las murallas eran rotas. Nuestra vida espiritual es como un castillo que necesita estar cimentado en Cristo y Su Palabra, lo cual nos permitirá resistir los numerosos ataques del enemigo en esta guerra espiritual. Se viven tiempos muy difíciles y es necesario estar en el fundamento, que es Cristo y la doctrina que nos fue enseñada, dándonos la estabilidad en medio de este mundo donde peregrinamos.', '"Por tanto, Jehová el Señor dice así: He aquí que yo he puesto en Sion por fundamento una piedra, piedra probada, angular, preciosa, de cimiento estable; el que creyere, no se apresure". Isaías 28:16"', 'https://mmmoficial.org/wp-content/uploads/2026/02/lema_mmm_estabilidad_2021.jpg'),
  ('2020', 'Kyrios', 'Cuando Jesús resucitó, fue recibido en gloria y exaltado por Dios, quien le otorgó un nombre que es sobre todo nombre para que toda lengua confiese que Él es el Señor (Kýrios). Jesús es la máxima autoridad en el universo y el Soberano de toda Su creación; una verdad que los primeros cristianos reconocieron con amor y sumisión, estando dispuestos a padecer vituperios, persecución y hasta la muerte por causa de Su nombre.



Hoy reafirmamos que Jesucristo sigue siendo el Amo y Señor de nuestras vidas, pues Él es quien gobierna y es el único dueño de esta obra misionera. Al rendir nuestra voluntad ante Su soberanía, permitimos que Su autoridad guíe cada paso del camino, reconociendo que solo bajo Su señorío la iglesia permanece firme y cumple su propósito eterno en la tierra.', '"Por lo cual Dios también le exaltó hasta lo sumo, y le dio un nombre que es sobre todo nombre, para que en el nombre de Jesús se doble toda rodilla de los que están en los cielos, y en la tierra, y debajo de la tierra" Filipenses 2:9-10"', 'https://mmmoficial.org/wp-content/uploads/2026/02/lema_mmm_kyrios_2020.jpg'),
  ('2019', 'Shekinah', 'Este lema nos declara que Dios habita en medio de Su pueblo, manifestándose como ese fuego que arde insistentemente y nos acompaña en cada instante del camino. Es Su presencia la que nos fortalece para seguir caminando firmes y constantes en las sendas del Evangelio, reconociendo que la guía del Espíritu Santo es el fundamento que sostiene nuestra fe ante cualquier adversidad.



Este año será un tiempo de gloria marcado por las manifestaciones de Dios y un avivamiento profundo del Espíritu Santo en cada corazón. Al mantener encendida la llama de Su presencia, aseguramos que la obra siga avanzando con poder, permitiendo que Su fuego purificador transforme vidas y cumpla el propósito divino en toda la congregación.', '"Porque donde están dos o tres congregados en mi nombre, allí estoy yo en medio de ellos". Mateo 18:20"', 'https://mmmoficial.org/wp-content/uploads/2026/02/lema_mmm_shekina_2019.jpg'),
  ('2018', 'Credibilidad', 'Vivimos en tiempos de gran desconfianza donde la mayoría de las organizaciones e instituciones públicas y privadas han perdido la credibilidad, convirtiéndose en una crisis para los ciudadanos. Frente a esta inestabilidad social les presentamos el nuevo lema para el 2018: \\"CREDIBILIDAD, cualidad que inspira confianza (1 Pedro 2:12)\\". Lema que nos dirige a la seguridad, bienestar y utilidad que se refleja. La persona creíble a través de los hechos embellecen su discurso\\"', '"Manteniendo buena vuestra manera de vivir entre los gentiles; para que en lo que murmuran de vosotros como de malhechores, glorifiquen a Dios en el día de la visitación, al considerar vuestras buenas obras". 1 Pedro 2:12"', 'https://mmmoficial.org/wp-content/uploads/2026/02/lema_mmm_credibilidad_2018.jpg'),
  ('2017', 'Responsabilidad', 'Este año, el Señor Jesucristo entregó para la obra del Movimiento Misionero Mundial la siguiente consigna: \\"Responsabilidad 2017, cualidad que no admite excusas - Romanos cap.14: vers.12\\", que orienta al cumplimiento de las obligaciones, a asumir compromisos y sus consecuencias, al no vivir del \\"no puedo\\" porque Dios dijo en su Palabra: \\"Todo lo puedo en Cristo que me fortalece\\", Filipenses 4:13.', '"De manera que cada uno de nosotros dará a Dios cuenta de sí". Romanos 14:12"', 'https://mmmoficial.org/wp-content/uploads/2026/02/lema_mmm_responsabilidad_2017.jpg');

INSERT INTO representantes (nombre, cargo, descripcion, foto, orden) VALUES
  ('Nombre Apellido', 'Presidente', 'Breve reseña del representante.', 'img/representantes/representante-01.jpg', 1),
  ('Nombre Apellido', 'Secretario/a', 'Breve reseña del representante.', 'img/representantes/representante-02.jpg', 2),
  ('Nombre Apellido', 'Tesorero/a', 'Breve reseña del representante.', 'img/representantes/representante-03.jpg', 3);

INSERT INTO horarios (dia, hora, actividad, lugar, orden) VALUES
  ('Miércoles', '7:00 - 8:00 p. m.', 'Culto de oración', 'Templo', 1),
  ('Viernes', '7:00 - 9:00 p. m.', 'Culto general', 'Templo', 2),
  ('Domingo', '10:00 a. m. - 1:00 p. m.', 'Escuela dominical', 'Templo', 3),
  ('Domingo', '3:00 - 6:00 p. m.', 'Culto de la tarde', 'Templo', 4);

INSERT INTO agenda_eventos (dia, nombre, hora, orden) VALUES
  (3, 'Culto de oración', '7:00 - 8:00 p. m.', 1),
  (5, 'Culto general', '7:00 - 9:00 p. m.', 1),
  (7, 'Escuela dominical', '10:00 a. m. - 1:00 p. m.', 1),
  (7, 'Culto de la tarde', '3:00 - 6:00 p. m.', 2);

INSERT INTO noticias (titulo, fecha, resumen, imagen) VALUES
  ('Título de la noticia 1', '2026-02-15', 'Resumen breve de la noticia para mostrar en la tarjeta.', 'img/noticias/noticia-01.jpg'),
  ('Título de la noticia 2', '2026-02-02', 'Resumen breve de la noticia para mostrar en la tarjeta.', 'img/noticias/noticia-02.jpg'),
  ('Título de la noticia 3', '2026-01-20', 'Resumen breve de la noticia para mostrar en la tarjeta.', 'img/noticias/noticia-03.jpg');
