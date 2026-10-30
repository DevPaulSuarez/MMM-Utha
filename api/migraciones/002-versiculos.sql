-- Versículo del día: a cada cuenta le toca uno distinto, sin repetir
-- en un año (ver versiculo.php). Los textos iniciales los carga 003.

-- temas: separados por coma, el primero es el principal (ver TEMAS en
-- versiculo.php). activo = 0: el pastor lo sacó de la rotación.
CREATE TABLE versiculos (
  id     INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  cita   VARCHAR(60)   NOT NULL,
  texto  VARCHAR(1000) NOT NULL,
  temas  VARCHAR(200)  NOT NULL DEFAULT '',
  activo TINYINT(1)    NOT NULL DEFAULT 1,
  creado DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- El versículo que le tocó a cada cuenta cada día. Se eligen por
-- adelantado unos días, para que el celular programe las
-- notificaciones. me_hablo: tocó "Sí, me habló".
CREATE TABLE versiculos_diarios (
  usuario_id   INT UNSIGNED NOT NULL,
  fecha        DATE         NOT NULL,
  versiculo_id INT UNSIGNED NOT NULL,
  me_hablo     TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (usuario_id, fecha),
  INDEX (usuario_id, versiculo_id),
  FOREIGN KEY (usuario_id)   REFERENCES usuarios(id)   ON DELETE CASCADE,
  FOREIGN KEY (versiculo_id) REFERENCES versiculos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Es para mi hermano": quién se lo mandó a quién. Al que lo recibe
-- no se le vuelve a elegir ese versículo por un año.
CREATE TABLE versiculos_compartidos (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  versiculo_id    INT UNSIGNED NOT NULL,
  de_usuario_id   INT UNSIGNED NOT NULL,
  para_usuario_id INT UNSIGNED NOT NULL,
  creado          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (para_usuario_id, creado),
  INDEX (de_usuario_id, creado),
  FOREIGN KEY (versiculo_id)    REFERENCES versiculos(id) ON DELETE CASCADE,
  FOREIGN KEY (de_usuario_id)   REFERENCES usuarios(id)   ON DELETE CASCADE,
  FOREIGN KEY (para_usuario_id) REFERENCES usuarios(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
