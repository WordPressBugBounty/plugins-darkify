(function () {
    
    const moonIcon = `<svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 16 16" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg"><path d="M6 .278a.77.77 0 0 1 .08.858 7.2 7.2 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277q.792-.001 1.533-.16a.79.79 0 0 1 .81.316.73.73 0 0 1-.031.893A8.35 8.35 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71 0 4.266 2.114 1.312 5.124.06A.75.75 0 0 1 6 .278"></path><path d="M10.794 3.148a.217.217 0 0 1 .412 0l.387 1.162c.173.518.579.924 1.097 1.097l1.162.387a.217.217 0 0 1 0 .412l-1.162.387a1.73 1.73 0 0 0-1.097 1.097l-.387 1.162a.217.217 0 0 1-.412 0l-.387-1.162A1.73 1.73 0 0 0 9.31 6.593l-1.162-.387a.217.217 0 0 1 0-.412l1.162-.387a1.73 1.73 0 0 0 1.097-1.097zM13.863.099a.145.145 0 0 1 .274 0l.258.774c.115.346.386.617.732.732l.774.258a.145.145 0 0 1 0 .274l-.774.258a1.16 1.16 0 0 0-.732.732l-.258.774a.145.145 0 0 1-.274 0l-.258-.774a1.16 1.16 0 0 0-.732-.732l-.774-.258a.145.145 0 0 1 0-.274l.774-.258c.346-.115.617-.386.732-.732z"></path></svg>`;
    const sunIcon = `<svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 24 24" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg"><path fill="none" d="M0 0h24v24H0z"></path><path d="M11 4V2c0-.55.45-1 1-1s1 .45 1 1v2c0 .55-.45 1-1 1s-1-.45-1-1zm7.36 3.05 1.41-1.42a.996.996 0 1 0-1.41-1.41l-1.41 1.42a.996.996 0 1 0 1.41 1.41zM22 11h-2c-.55 0-1 .45-1 1s.45 1 1 1h2c.55 0 1-.45 1-1s-.45-1-1-1zm-10 8c-.55 0-1 .45-1 1v2c0 .55.45 1 1 1s1-.45 1-1v-2c0-.55-.45-1-1-1zM5.64 7.05 4.22 5.64c-.39-.39-.39-1.03 0-1.41s1.03-.39 1.41 0l1.41 1.41c.39.39.39 1.03 0 1.41s-1.02.39-1.4 0zm11.31 9.9a.996.996 0 0 0 0 1.41l1.41 1.41c.39.39 1.03.39 1.41 0a.996.996 0 0 0 0-1.41l-1.41-1.41a.996.996 0 0 0-1.41 0zM2 13h2c.55 0 1-.45 1-1s-.45-1-1-1H2c-.55 0-1 .45-1 1s.45 1 1 1zm3.64 6.78 1.41-1.41c.39-.39.39-1.03 0-1.41s-1.03-.39-1.41 0l-1.41 1.41a.996.996 0 0 0 0 1.41c.38.39 1.02.39 1.41 0zM12 6c-3.31 0-6 2.69-6 6s2.69 6 6 6 6-2.69 6-6-2.69-6-6-6z"></path></svg>`;

    
    tinymce.PluginManager.add('darkify_button', function (editor, url) {
        
        editor.addButton('darkify_button', {
            text: ``,
            icon: 'darkify_button',
            tooltip: 'Toggle Dark Mode',
            onclick: function () {
                let currentMode = localStorage.getItem("darkify_classic_editor_mode") || 0;
                
                if ( currentMode == 1 ) {
                    removeDarkModeOnMCE();
                } else {
                    applyDarkModeOnMCE();
                }
            },
            onPostRender: function () {
                // inject icon manually after rendering
                const btnEl = this.getEl();
                const savedMode = localStorage.getItem("darkify_classic_editor_mode") || "0";
                btnEl.innerHTML = '<button class="mce-dark-mode-button">' + (savedMode == 1 ? sunIcon : moonIcon) + '</button>'
            }
        });

        editor.on('init', function () {
            initDarkModeOnMCE();
            document.addEventListener('darkify', ( event ) => {
                if ( event.detail.isActive ) {
                    applyDarkModeOnMCE();
                } else {
                    removeDarkModeOnMCE();
                }
            });
        });
        
    });



    /**
     * The Dark Reader admin engine, when this page is running it.
     *
     * Where it is present it owns the TinyMCE frame: it reads the same
     * localStorage key this button writes, so it paints the frame with the
     * chosen palette and the hand-written stylesheet below is not only
     * redundant but wrong — it hardcodes rgb(32,35,36) and skyblue links,
     * ignoring the palette entirely.
     */
    function darkifyEngine() {
        const engine = window.darkifyAdminEngine;
        return engine && typeof engine.reapply === "function" ? engine : null;
    }

    // Function to apply theme and save to localStorage
    function applyDarkModeOnMCE() {
        localStorage.setItem("darkify_classic_editor_mode", 1);

        const engine = darkifyEngine();
        if ( engine ) {
            engine.reapply();
            setButtonIcon(sunIcon);
            return;
        }

        const iframe = document.querySelector('.mce-container-body iframe');
        if ( iframe ) {
            // Add css to iframe.
            const css = `body { background-color: rgb(32, 35, 36);
            color: #f0f0f0 !important;
            width: 100% !important;
            font-size: inherit !important;
            margin: inherit !important;
            }
            a {
                color: skyblue !important;
            }
            `;
            const style = document.createElement('style');  
            style.textContent = css;
            style.id = 'wp-dark-mode-classic-editor-style';
            iframe.contentDocument.head.appendChild(style);
        }

        // Update the button icon.
        const btnEl = document.querySelector('.mce-dark-mode-button');
        if ( btnEl ) {
            btnEl.innerHTML = sunIcon
        }
    }

    function setButtonIcon(icon) {
        const btnEl = document.querySelector('.mce-dark-mode-button');
        if ( btnEl ) {
            btnEl.innerHTML = icon;
        }
    }

    function removeDarkModeOnMCE() {
        localStorage.setItem("darkify_classic_editor_mode", 0);

        const engine = darkifyEngine();
        if ( engine ) {
            engine.reapply();
            setButtonIcon(moonIcon);
            return;
        }

        const iframe = document.querySelector('.mce-container-body iframe');
        if ( iframe ) {
            // Search the style inside the iframe.
            const style = iframe.contentDocument.querySelector('#wp-dark-mode-classic-editor-style');
            if ( style ) {
                style.remove();
            }
        }

        // Update the button icon.
        const btnEl = document.querySelector('.mce-dark-mode-button');
        if ( btnEl ) {
            btnEl.innerHTML = moonIcon;
        }
    }

    // Initialize Classic Editor Dark Mode.
    const initDarkModeOnMCE = () => {
        const engine = darkifyEngine();

        // With no remembered choice, follow the admin. Seeding the key rather
        // than just reading through to it keeps the button's icon honest: the
        // engine defaults this frame to the admin state too, so leaving the key
        // unset would show a moon over an already-dark frame.
        if ( engine && localStorage.getItem("darkify_classic_editor_mode") === null ) {
            localStorage.setItem("darkify_classic_editor_mode", engine.isDark() ? 1 : 0);
        }

        const savedMode = localStorage.getItem("darkify_classic_editor_mode") || 0;
        
        if ( savedMode == 1 ) {
            applyDarkModeOnMCE();
        } else {
            removeDarkModeOnMCE();
        }
    }
})();
