# Summary

O autosave da `CupomView` deixou de disparar requests concorrentes. O frontend agora trabalha em `single-flight`, reenvia apenas quando houve nova edicao durante o save e recarrega o estado derivado do cupom logo apos a persistencia, eliminando a necessidade de atualizar a pagina para ver a fase seguinte.
