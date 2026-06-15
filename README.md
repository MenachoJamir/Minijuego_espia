# 🕵️ El Espía en el Templo

Juego de deducción social multijugador con temática bíblica, desarrollado como proyecto personal con PHP, MySQL y JavaScript.

## ¿De qué trata?

Entre 4 y 8 jugadores participan en cada partida. A todos se les asigna una escena bíblica secreta... menos a uno: el **espía**. El espía no sabe dónde están, y debe descubrirlo escuchando las respuestas de los demás sin delatarse. El grupo debe identificar al espía antes de que él adivine la escena.

## Funcionalidades

- Partidas multijugador de 4 a 8 jugadores en un mismo dispositivo
- Asignación aleatoria del rol de espía cada partida
- Sistema de turnos, rondas y votaciones gestionado en sesión PHP
- 3 niveles de dificultad: fácil, medio y difícil
- Pistas de contexto aleatorias para ayudar a los jugadores legítimos
- Historial de partidas guardado en base de datos
- Panel de administración para gestionar escenas bíblicas (crear, editar, activar/desactivar, eliminar)
- Comunicación cliente-servidor mediante AJAX sin recargar la página

## Tecnologías

- **Backend:** PHP 8 con PDO
- **Base de datos:** MySQL (auto-creación de tablas al iniciar)
- **Frontend:** JavaScript vanilla, CSS personalizado
- **Servidor local:** Laragon

## Estructura del proyecto

```
espia_templo/
├── index.php          # Lógica del juego + API AJAX
├── admin/
│   ├── index.php      # Panel de administración de escenas
│   └── historial.php  # Historial de partidas jugadas
├── assets/
│   ├── juego.js       # Lógica del cliente (turnos, votaciones, UI)
│   └── style.css      # Estilos del juego
└── includes/
    └── db.php         # Conexión PDO y creación automática de tablas
```

## Cómo ejecutar

1. Tener instalado [Laragon](https://laragon.org/) o XAMPP
2. Clonar o copiar la carpeta en `C:/laragon/www/`
3. Abrir el navegador en `http://localhost/espia_templo`
4. La base de datos `espia_templo_db` se crea automáticamente al primer acceso

## Capturas

> ## Capturas

![Pantalla de inicio](espia_templo/Captura%20de%20pantalla%202026-06-14%20205454.png)
![Vista del juego](espia_templo/Captura%20de%20pantalla%202026-06-14%20205902.png)
![Turno de preguntas](espia_templo/Captura%20de%20pantalla%202026-06-14%20210106.png)
![Votación](espia_templo/Captura%20de%20pantalla%202026-06-14%20210155.png)
## Autor

**Jamir Menacho** — Estudiante de Desarrollo de Software en SENATI, Lima, Perú  
[github.com/MenachoJamir](https://github.com/MenachoJamir)
