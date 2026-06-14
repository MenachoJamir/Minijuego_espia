<?php
// ══════════════════════════════════════════════
//  EL ESPÍA EN EL TEMPLO — Configuración BD
// ══════════════════════════════════════════════
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'espia_templo_db');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    try {
        $i = new PDO("mysql:host=".DB_HOST.";charset=utf8mb4", DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $i->exec("CREATE DATABASE IF NOT EXISTS `".DB_NAME."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        crearTablas($pdo);
    } catch (PDOException $e) {
        die('<pre style="color:red">Error BD: '.$e->getMessage().'</pre>');
    }
    return $pdo;
}

function crearTablas(PDO $db): void {
    // Escenas bíblicas
    $db->exec("CREATE TABLE IF NOT EXISTS escenas (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        titulo      VARCHAR(120) NOT NULL,
        descripcion TEXT,
        libro       VARCHAR(60),
        dificultad  ENUM('facil','medio','dificil') DEFAULT 'medio',
        activa      TINYINT(1) DEFAULT 1,
        creada_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Pistas de contexto para cada escena (ayudan a los no-espías a dar respuestas creíbles)
    $db->exec("CREATE TABLE IF NOT EXISTS pistas_escena (
        id        INT AUTO_INCREMENT PRIMARY KEY,
        escena_id INT NOT NULL,
        pista     VARCHAR(200) NOT NULL,
        FOREIGN KEY (escena_id) REFERENCES escenas(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // Historial de partidas
    $db->exec("CREATE TABLE IF NOT EXISTS partidas (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        escena_id       INT NOT NULL,
        jugadores_json  JSON NOT NULL,
        espia_nombre    VARCHAR(80) NOT NULL,
        espia_adivinado TINYINT(1) DEFAULT 0,
        ganador         ENUM('espia','grupo') DEFAULT 'grupo',
        jugada_en       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (escena_id) REFERENCES escenas(id)
    ) ENGINE=InnoDB");

    // Puntajes acumulados
    $db->exec("CREATE TABLE IF NOT EXISTS puntajes (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        jugador    VARCHAR(80) NOT NULL UNIQUE,
        victorias  INT DEFAULT 0,
        derrotas   INT DEFAULT 0,
        como_espia INT DEFAULT 0,
        puntos     INT DEFAULT 0,
        actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Sembrar datos si vacío
    if ($db->query("SELECT COUNT(*) FROM escenas")->fetchColumn() == 0) {
        sembrarDatos($db);
    }
}

function sembrarDatos(PDO $db): void {
    $escenas = [
        ['La Última Cena', 'Jesús comparte la cena de Pascua con sus doce discípulos la noche antes de su crucifixión, instituye la Santa Cena.', 'Juan 13', 'facil', [
            'Mesa grande con pan y vino', 'Jesús lavó los pies de sus discípulos', 'Uno de los presentes traicionará a Jesús', 'Doce personas sentadas juntas', 'Sala del piso de arriba en Jerusalén'
        ]],
        ['El Bautismo de Jesús', 'Juan el Bautista bautiza a Jesús en el río Jordán y el Espíritu Santo desciende como paloma mientras una voz del cielo habla.', 'Mateo 3', 'facil', [
            'Agua del río', 'Una paloma descendió del cielo', 'Voz de Dios desde el cielo', 'Presencia de Juan el Bautista', 'Jesús salió del agua'
        ]],
        ['David y Goliat', 'El joven pastor David derrota al gigante filisteo Goliat con una honda y una piedra, ante los ejércitos de Israel y Filistea.', '1 Samuel 17', 'facil', [
            'Campo de batalla entre dos ejércitos', 'Un gigante con armadura pesada', 'Una honda y cinco piedras lisas', 'El gigante cayó al suelo', 'Gritos de guerra de dos naciones'
        ]],
        ['Noé y el Arca', 'Dios instruye a Noé para construir un arca enorme. Noé, su familia y parejas de todos los animales entran antes del gran diluvio.', 'Génesis 6-7', 'facil', [
            'Una embarcación gigantesca de madera', 'Animales de todas las especies en pares', 'La familia completa del patriarca', 'Lluvia torrencial que dura 40 días', 'Un arcoíris al final como señal de pacto'
        ]],
        ['La Transfiguración', 'Jesús se transfigura en el monte y su rostro brilla como el sol. Moisés y Elías aparecen junto a él mientras Pedro, Jacobo y Juan observan.', 'Mateo 17', 'medio', [
            'Un monte alto y solitario', 'Ropa resplandeciente y blanca', 'Dos figuras del Antiguo Testamento presentes', 'Una nube luminosa que los cubre', 'Tres discípulos testigos'
        ]],
        ['El Juicio de Salomón', 'Dos mujeres reclaman ser madres de un bebé. El rey Salomón ordena partir al niño en dos para descubrir quién es la verdadera madre.', '1 Reyes 3', 'medio', [
            'El trono real con el rey sentado', 'Dos mujeres disputando acaloradamente', 'Un bebé en el centro de la disputa', 'Una espada desenvainada sobre el niño', 'Una madre que renuncia a su hijo por amor'
        ]],
        ['La Caída de Jericó', 'El ejército de Israel marcha alrededor de las murallas de Jericó durante siete días. Al sonar las trompetas y gritar el pueblo, las murallas caen.', 'Josué 6', 'medio', [
            'Murallas enormes de una ciudad antigua', 'Sacerdotes cargando el arca del pacto', 'Siete trompetas sonando al unísono', 'Un ejército marchando en silencio', 'Las murallas colapsan de golpe'
        ]],
        ['Pentecostés', 'El Espíritu Santo desciende sobre los 120 discípulos reunidos en el aposento alto, con sonido de viento fuerte y lenguas de fuego sobre cada uno.', 'Hechos 2', 'medio', [
            'Una habitación llena de personas orando', 'Lenguas de fuego sobre cada cabeza', 'Sonido de un viento recio y poderoso', 'Personas hablando en otros idiomas', 'Multitud afuera asombrada por lo que ve'
        ]],
        ['Daniel en el Foso de los Leones', 'Daniel es arrojado al foso de los leones por seguir orando a Dios, pero un ángel cierra las bocas de los leones y Daniel sobrevive ileso.', 'Daniel 6', 'medio', [
            'Un pozo profundo con leones rugientes', 'Un ángel con los leones calmados', 'Una piedra sellada sobre la entrada del foso', 'El rey angustiado que no puede dormir', 'Daniel de rodillas orando tres veces al día'
        ]],
        ['La Tempestad Calmada', 'Jesús y sus discípulos están en una barca en el mar de Galilea. Una tormenta violenta amenaza con hundirlos. Jesús reprende al viento y al mar y todo queda en calma.', 'Marcos 4', 'medio', [
            'Una barca pequeña en el agua', 'Olas enormes golpeando la embarcación', 'Discípulos aterrados gritando de miedo', 'Jesús dormido en la popa sobre una almohada', 'Mar completamente en calma de repente'
        ]],
        ['La Zarza Ardiente', 'Moisés pastorea las ovejas en el desierto cuando ve una zarza que arde pero no se consume. Dios le habla desde la zarza y lo comisiona para liberar a Israel.', 'Éxodo 3', 'dificil', [
            'Desierto árido con rocas y arena', 'Una zarza que arde sin consumirse', 'Tierra santa: Moisés se quita las sandalias', 'Voz poderosa saliendo del fuego', 'Un pastor con su cayado y sus ovejas'
        ]],
        ['La Resurrección de Lázaro', 'Jesús llega cuatro días después de la muerte de Lázaro. Ante la tumba sellada, Jesús ora y llama a Lázaro, quien sale vivo con las vendas de enterrar.', 'Juan 11', 'dificil', [
            'Una cueva sellada con una piedra grande', 'Muchas personas llorando alrededor', 'Olor fuerte según los presentes', 'Un hombre saliendo envuelto en telas de lino', 'Jesús llorando antes del milagro'
        ]],
        ['La Torre de Babel', 'Los descendientes de Noé intentan construir una torre que llegue al cielo para hacerse famosos. Dios confunde sus lenguas y los dispersa por toda la tierra.', 'Génesis 11', 'dificil', [
            'Una construcción altísima en plena llanura', 'Muchos trabajadores de diferentes grupos', 'Confusión repentina en la comunicación', 'Personas que de pronto no se entienden entre sí', 'Una ciudad que quedó abandonada e incompleta'
        ]],
        ['Elías en el Monte Carmelo', 'Elías desafía a 450 profetas de Baal. Ambos preparan altares. El fuego de Dios consume el sacrificio de Elías completamente, incluyendo el agua alrededor del altar.', '1 Reyes 18', 'dificil', [
            'Un monte con dos altares frente a frente', 'Fuego que cae del cielo repentinamente', 'Cientos de profetas rivales presentes', 'Un sacrificio empapado en agua', 'El pueblo que cae de rodillas al ver el fuego'
        ]],
        ['La Visión de Ezequiel', 'El profeta Ezequiel tiene una visión extraordinaria junto al río Quebar: cuatro criaturas vivientes con cuatro rostros, ruedas dentro de ruedas y el trono de Dios.', 'Ezequiel 1', 'dificil', [
            'Orilla de un río en tierra extranjera', 'Cuatro criaturas con alas y múltiples rostros', 'Ruedas inmensas llenas de ojos que giran', 'Destellos de relámpagos y fuego brillante', 'Una figura sobre un trono de zafiro en las alturas'
        ]],
    ];

    $sE  = $db->prepare("INSERT INTO escenas (titulo, descripcion, libro, dificultad) VALUES (?,?,?,?)");
    $sP  = $db->prepare("INSERT INTO pistas_escena (escena_id, pista) VALUES (?,?)");
    foreach ($escenas as [$titulo, $desc, $libro, $dif, $pistas]) {
        $sE->execute([$titulo, $desc, $libro, $dif]);
        $eid = (int)$db->lastInsertId();
        foreach ($pistas as $p) $sP->execute([$eid, $p]);
    }
}
