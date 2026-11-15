-- Grupo de cada representante: el rol fijo que decide su sección y
-- su color en la web (Pastor, Co-pastor, Diácono, Líder de…, Miembro,
-- En proceso). "cargo" sigue siendo texto libre (Tesorero, Cantante…).
-- Los valores válidos están en secciones.php · representantes · grupo.
ALTER TABLE representantes
  ADD COLUMN grupo VARCHAR(30) NOT NULL DEFAULT 'miembro' AFTER cargo;

-- Un primer grupo sacado del cargo que ya tienen; el pastor lo
-- corrige desde la app. El orden importa: "Co-pastora" antes que
-- "Pastora", y la forma femenina antes que la masculina.
UPDATE representantes SET grupo = CASE
  WHEN cargo LIKE '%co%pastora%'   THEN 'copastora'
  WHEN cargo LIKE '%co%pastor%'    THEN 'copastor'
  WHEN cargo LIKE '%pastora%'      THEN 'pastora'
  WHEN cargo LIKE '%pastor%'       THEN 'pastor'
  WHEN cargo LIKE '%diaconisa%'    THEN 'diaconisa'
  WHEN cargo LIKE '%di_cono%'      THEN 'diacono'
  WHEN cargo LIKE '%caballeros%'   THEN 'lider_caballeros'
  WHEN cargo LIKE '%damas%'        THEN 'lider_damas'
  WHEN cargo LIKE '%j_venes%'      THEN 'lider_jovenes'
  WHEN cargo LIKE '%ni_os%'        THEN 'lider_ninos'
  WHEN cargo LIKE '%evangelismo%'  THEN 'lider_evangelismo'
  WHEN cargo LIKE '%alabanza%'     THEN 'lider_alabanza'
  WHEN cargo LIKE '%escuela%'      THEN 'lider_escuela'
  WHEN cargo LIKE '%actividades%'  THEN 'lider_actividades'
  WHEN cargo LIKE '%multimedia%'   THEN 'lider_multimedia'
  ELSE 'miembro'
END;
