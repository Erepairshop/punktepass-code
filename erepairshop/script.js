document.addEventListener('DOMContentLoaded', function() {
    const sicherheitstypSelect = document.getElementById('sicherheitstyp');
    const musterInput = document.getElementById('muster_input');
    const canvas = document.getElementById('musterCanvas');

    // Funktion zum Ein- und Ausblenden der Eingabefelder
    function toggleFields() {
        if (sicherheitstypSelect.value === 'pin') {
            musterInput.style.display = 'none';
        } else if (sicherheitstypSelect.value === 'muster') {
            musterInput.style.display = 'block';
            // Überprüfe, ob das Canvas-Element vorhanden ist, bevor es vorbereitet wird
            if (canvas) {
                prepareCanvas();
            }
        } else {
            musterInput.style.display = 'none';
        }
    }

    // Initialisiere Event-Listener für das Dropdown-Menü
    sicherheitstypSelect.addEventListener('change', toggleFields);

    // Funktion zum Vorbereiten des Canvas
    function prepareCanvas() {
        const ctx = canvas.getContext('2d');
        // Setze das Canvas zurück
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.strokeStyle = "#000000"; // Farbe der Linie
        ctx.lineWidth = 2; // Dicke der Linie

        let isDrawing = false;

        // Beginne das Zeichnen
        function startDrawing(e) {
            isDrawing = true;
            ctx.beginPath();
            // Bewege den Pfad zum Startpunkt, abhängig von der Mausposition
            ctx.moveTo(e.offsetX, e.offsetY);
        }

        // Zeichne auf dem Canvas
        function draw(e) {
            if (!isDrawing) return;
            ctx.lineTo(e.offsetX, e.offsetY);
            ctx.stroke();
        }

        // Beende das Zeichnen
        function stopDrawing() {
            isDrawing = false;
        }

        // Füge Event-Listener zum Canvas hinzu
        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseout', stopDrawing);
    }

    // Stelle sicher, dass die Felder beim Laden der Seite korrekt angezeigt werden
    toggleFields();
});
