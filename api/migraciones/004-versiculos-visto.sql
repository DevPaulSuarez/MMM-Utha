-- visto: cuándo abrió la persona el versículo de ese día (en la app o
-- desde la notificación). Las estadísticas cuentan "me habló" sobre
-- los que lo abrieron, no sobre los repartidos.
-- para_hermano: tocó "Es para mi hermano": no le habló a él, le hizo
-- pensar en otra persona.
ALTER TABLE versiculos_diarios
  ADD visto        DATETIME   NULL,
  ADD para_hermano TINYINT(1) NOT NULL DEFAULT 0;
