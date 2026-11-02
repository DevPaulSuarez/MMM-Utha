-- Talento: los domingos alguien prepara comida que se vende después
-- del culto. Lo que se reúne va para la iglesia.

-- Un talento por día. precio: lo que cuesta cada plato.
-- cobrador_id: la cuenta que cobra los platos ese día (opcional; la
-- tesorería y los administradores también pueden cobrar).
CREATE TABLE talentos (
  id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  fecha       DATE          NOT NULL UNIQUE,
  platillo    VARCHAR(150)  NOT NULL,
  descripcion TEXT          NOT NULL,
  precio      DECIMAL(10,2) NOT NULL,
  cobrador_id INT UNSIGNED  NULL,
  creado      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cobrador_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quiénes preparan la comida: un representante o, si no tiene ficha,
-- solo su nombre.
CREATE TABLE talento_cocineros (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  talento_id       INT UNSIGNED NOT NULL,
  representante_id INT UNSIGNED NULL,
  nombre           VARCHAR(150) NOT NULL DEFAULT '',
  orden            INT          NOT NULL DEFAULT 0,
  FOREIGN KEY (talento_id) REFERENCES talentos(id) ON DELETE CASCADE,
  FOREIGN KEY (representante_id) REFERENCES representantes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cada cobro: cuántos platos (personas que comen), cómo pagaron
-- ("efectivo" o "traspaso") y cuánto. recibido: con cuánto pagó en
-- efectivo, para el vuelto (null en traspasos).
CREATE TABLE talento_ventas (
  id         INT UNSIGNED      AUTO_INCREMENT PRIMARY KEY,
  talento_id INT UNSIGNED      NOT NULL,
  platos     SMALLINT UNSIGNED NOT NULL,
  metodo     VARCHAR(10)       NOT NULL,
  monto      DECIMAL(10,2)     NOT NULL,
  recibido   DECIMAL(10,2)     NULL,
  usuario_id INT UNSIGNED      NULL,
  creado     DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (talento_id, creado),
  FOREIGN KEY (talento_id) REFERENCES talentos(id) ON DELETE CASCADE,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
