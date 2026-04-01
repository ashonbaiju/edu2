// Add interaction for icon buttons to simulate physical pressing
document.addEventListener('DOMContentLoaded', () => {
    const buttons = document.querySelectorAll('.icon-btn, .nav-item');
    
    buttons.forEach(btn => {
        btn.addEventListener('mousedown', function() {
            if(!this.classList.contains('primary-fill') && this.tagName === 'BUTTON') {
                this.style.boxShadow = 'var(--neu-concave)';
            }
        });
        
        btn.addEventListener('mouseup', function() {
            if(!this.classList.contains('primary-fill') && this.tagName === 'BUTTON') {
                this.style.boxShadow = '';
            }
        });
        
        btn.addEventListener('mouseleave', function() {
            if(!this.classList.contains('primary-fill') && this.tagName === 'BUTTON') {
                this.style.boxShadow = '';
            }
        });
    });

    // Make nav items active on click
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            navItems.forEach(nav => nav.classList.remove('active'));
            item.classList.add('active');
        });
    });
});
