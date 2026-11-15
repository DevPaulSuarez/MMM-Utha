-- Grupos nuevos: tesorero/a y secretario/a (bloque Pastoral, apoyo
-- administrativo) y maestro/a (Ministerios). Los valores válidos están
-- en secciones.php; aquí solo se acomodan los que ya existen: a quien
-- todavía es "miembro" y su cargo lo dice, se le da su grupo.
UPDATE representantes SET grupo = CASE
  WHEN cargo LIKE '%tesorera%'    THEN 'tesorera'
  WHEN cargo LIKE '%tesorer%'     THEN 'tesorero'
  WHEN cargo LIKE '%secretaria%'  THEN 'secretaria'
  WHEN cargo LIKE '%secretari%'   THEN 'secretario'
  WHEN cargo LIKE '%maestra%'     THEN 'maestra'
  WHEN cargo LIKE '%maestro%'     THEN 'maestro'
  ELSE grupo
END
WHERE grupo = 'miembro';
