-- Metas de la iglesia y lo que el tesorero registra para cada una.
-- Rol nuevo en usuarios.rol: "tesorero" (registra las ofrendas).

-- tipo: "mensual" (gasto fijo: alquiler, agua; se cuenta de nuevo cada
-- mes) o "unica" (una compra o proyecto: una cámara; se cuenta todo).
-- activa = 0: el pastor la ocultó; los miembros ya no la ven.
CREATE TABLE metas (
  id           INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  titulo       VARCHAR(150)  NOT NULL,
  descripcion  TEXT          NOT NULL,
  tipo         VARCHAR(10)   NOT NULL DEFAULT 'mensual',
  monto        DECIMAL(10,2) NOT NULL,
  fecha_limite DATE          NULL,
  activa       TINYINT(1)    NOT NULL DEFAULT 1,
  orden        INT           NOT NULL DEFAULT 0,
  creado       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lo que se contó un día para una meta. Borrar la meta borra sus aportes.
CREATE TABLE aportes (
  id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  meta_id    INT UNSIGNED  NOT NULL,
  fecha      DATE          NOT NULL,
  diezmos    DECIMAL(10,2) NOT NULL DEFAULT 0,
  ofrendas   DECIMAL(10,2) NOT NULL DEFAULT 0,
  nota       VARCHAR(200)  NOT NULL DEFAULT '',
  usuario_id INT UNSIGNED  NULL,
  creado     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (meta_id, fecha),
  FOREIGN KEY (meta_id) REFERENCES metas(id) ON DELETE CASCADE,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
