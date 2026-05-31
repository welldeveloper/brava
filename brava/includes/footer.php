        </main>
        <!-- FIM CONTEÚDO -->

    </div><!-- fim área principal -->
</div><!-- fim flex layout -->

<script>
// Toggle da sidebar no mobile
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    sidebar.classList.toggle('-translate-x-full');
    overlay.classList.toggle('hidden');
}

// Fecha flash message automaticamente após 5s
const flash = document.getElementById('flash-msg');
if (flash) setTimeout(() => flash.remove(), 5000);
</script>

</body>
</html>
