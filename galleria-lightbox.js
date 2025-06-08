//alert('✅ lightbox script caricato');
        let currentIndex = 0;
        let rewindInterval = null;
        let isRewinding = false;
        let forwardInterval = null;
        let isForwarding = false;
        
        
        
        
        function dirTesto(gradi) {
          if (gradi != null){
          const directions = [
            "N", "NNE", "NE", "ENE", "E", "ESE", "SE", "SSE",
            "S", "SSW", "SW", "WSW", "W", "WNW", "NW", "NNW"
          ];
          let index = Math.round(gradi / 22.5) % 16;
          return directions[index];}
          else
          {return "--";}
        }



        function aggiornaLightbox() {
            const record = window.images[currentIndex]; // <--- questo è il tuo oggetto dati
            if (!record) return;

            const tempImg = new Image();
            tempImg.src = record.src;

            tempImg.onload = function () {
                const canvas = document.createElement('canvas');
                canvas.width = tempImg.width;
                canvas.height = tempImg.height - 80;
                const ctx = canvas.getContext('2d');

                ctx.drawImage(
                    tempImg,
                    0, 0,
                    tempImg.width, tempImg.height - 80,
                    0, 0,
                    tempImg.width, tempImg.height - 80
                );

                const croppedSrc = canvas.toDataURL();
                document.getElementById('lightbox-img').src = croppedSrc;
            };

            document.getElementById('lightbox-info').textContent = `${record.data_ora ?? 'N/A'} | T ${record.temp ?? 'N/A'}°C | UR ${record.hr ?? 'N/A'}% |️ ${record.p_hpa ?? 'N/A'} hPa | Vento ${record.vento ?? 'N/A'} km/h, ${dirTesto(record.dir) ?? 'N/A'}`;
        }

        //gestione da tastiera *********************
        let leftHoldInterval  = null;
        let rightHoldInterval = null;
        const HOLD_DELAY = 1000 / 3; // ~333ms → 3 passi/sec
        
          document.addEventListener("keydown", function(event) {
          const lightbox = document.getElementById("lightbox");
          if (!lightbox.classList.contains("active")) return;
        
          switch (event.key) {
            case " ":        // ← Spazio
            case "Spacebar": // per compatibilità
              event.preventDefault();  // evita scroll della pagina
              if (isRewinding) {
                rewindToCurrent();      // entra nella logica di “pausa”
              }
              else if (isForwarding) {
                forwardToNewest();      // idem per forward
              }
              break;
        
            case "Escape":
              closeLightbox();
              break;
        
            case "ArrowLeft":
              // … (resto identico)
              if (currentIndex < images.length - 1) {
                currentIndex++;
                aggiornaLightbox();
                updateNavButtons();
              }
              if (leftHoldInterval === null) {
                leftHoldInterval = setInterval(() => {
                  if (currentIndex < images.length - 1) {
                    currentIndex++;
                    aggiornaLightbox();
                    updateNavButtons();
                  } else {
                    clearInterval(leftHoldInterval);
                    leftHoldInterval = null;
                  }
                }, HOLD_DELAY);
              }
              break;
        
            case "ArrowRight":
              // … (resto identico)
              if (currentIndex > 0) {
                currentIndex--;
                aggiornaLightbox();
                updateNavButtons();
              }
              if (rightHoldInterval === null) {
                rightHoldInterval = setInterval(() => {
                  if (currentIndex > 0) {
                    currentIndex--;
                    aggiornaLightbox();
                    updateNavButtons();
                  } else {
                    clearInterval(rightHoldInterval);
                    rightHoldInterval = null;
                  }
                }, HOLD_DELAY);
              }
              break;
          }
        });
        
        document.addEventListener("keyup", function(event) {
          if (event.key === "ArrowLeft" && leftHoldInterval !== null) {
            clearInterval(leftHoldInterval);
            leftHoldInterval = null;
          }
          if (event.key === "ArrowRight" && rightHoldInterval !== null) {
            clearInterval(rightHoldInterval);
            rightHoldInterval = null;
          }
        });
        //gestione da tastiera *********************
        
        function showImage(record) {
  
          // chiama aggiornaLightbox() per centralizzare il rendering
          aggiornaLightbox();
        }
        
        
        function openLightbox(index) {
          const items = window.images || [];
          console.log('💡 openLightbox → array corrente:', items);
          console.log(`💡 openLightbox → indice richiesto: ${index}, totale immagini: ${items.length}`);
          if (!items.length) return;
        
          // 1) Imposta l'indice e mostra l'immagine
          currentIndex = index;
          aggiornaLightbox();
        
          // 2) Attiva la lightbox
          const lb = document.getElementById('lightbox');
          lb.classList.add('active');
        
          // 3) Mostra i pulsanti
          const btns = [
            { id: 'close-btn',   display: 'block' },
            { id: 'rewind-btn',  display: 'flex'  },
            { id: 'forward-btn', display: 'flex'  }
          ];
          btns.forEach(({id, display}) => {
            const btn = document.getElementById(id);
            if (btn) {
              btn.style.display = display;
              btn.disabled = false;  // resetta sempre lo stato
            }
        });

          // 4) Ora aggiorno lo stato "disabled" su ciascun bottone
          updateNavButtons();
        }
        
        document.addEventListener("DOMContentLoaded", () => {
        const closeBtn   = document.getElementById("close-btn");
          const rewindBtn  = document.getElementById("rewind-btn");
          const forwardBtn = document.getElementById("forward-btn");
        
          if (closeBtn) {
            closeBtn.addEventListener("click", closeLightbox);
          }
          if (rewindBtn) {
            rewindBtn.addEventListener("click", rewindToCurrent);
          }
          if (forwardBtn) {
            forwardBtn.addEventListener("click", forwardToNewest);
          }
        
        if (rewindInterval) {
            clearInterval(rewindInterval);
            rewindInterval = null;
            isRewinding = false;
            document.getElementById('rewind-btn').textContent = "⏪";
          }
        
        });
        
        
        
        function rewindToCurrent() {
          const rewindBtn = document.getElementById("rewind-btn");
          const rewindIcon = document.getElementById("rewind-icon");
        
          if (isRewinding) {
            // 🔻 Pausa: interrompi
            clearInterval(rewindInterval);
            rewindInterval = null;
            isRewinding = false;
        
            // 🔄 Torna al simbolo REWIND
            rewindIcon.innerHTML = `
             <path d="M11 12L20 6V18L11 12Z" />
            <path d="M4 12L13 6V18L4 12Z" />
            `
            return;
          }
        
          // ▶️ Avvia REWIND
          const targetIndex = currentIndex;
          currentIndex = images.length - 1;
          aggiornaLightbox();
          updateNavButtons();
          isRewinding = true;
        
          // 🔄 Cambia icona in PAUSA
          rewindIcon.innerHTML = `
            <rect x="6" y="4" width="5" height="16" />
            <rect x="14" y="4" width="5" height="16" />
          `;
        
          rewindInterval = setInterval(() => {
            if (currentIndex <= targetIndex) {
              clearInterval(rewindInterval);
              rewindInterval = null;
              isRewinding = false;
        
              // Torna a icona REWIND alla fine
              rewindIcon.innerHTML = `
                <path d="M11 12L20 6V18L11 12Z" />
            <path d="M4 12L13 6V18L4 12Z" />`;
              return;
            }
            currentIndex--;
            aggiornaLightbox();
            updateNavButtons();
          }, 300); // 3 immagini/sec
        }
        
        
        function forwardToNewest() {
          const forwardBtn = document.getElementById("forward-btn");
          const forwardIcon = document.getElementById("forward-icon");
        
          // 🔒 Se già in pausa → annulla
          if (isForwarding) {
            clearInterval(forwardInterval);
            forwardInterval = null;
            isForwarding = false;
        
            forwardIcon.innerHTML = `
              <path d="M11 12L20 6V18L11 12Z" />
              <path d="M4 12L13 6V18L4 12Z" />
            `;
            return;
  }

          // 🔒 Se siamo già all'immagine 0, disattiva
          if (currentIndex === 0) {
            forwardBtn.disabled = true;
            forwardIcon.innerHTML = `
              <path d="M11 12L20 6V18L11 12Z" />
              <path d="M4 12L13 6V18L4 12Z" />`;
            return;
          }
        
          // 🛑 Ferma rewind se attivo
          if (rewindInterval) {
            clearInterval(rewindInterval);
            rewindInterval = null;
            isRewinding = false;
            document.getElementById("rewind-icon").innerHTML = `
              <path d="M13 12L4 6V18L13 12Z" />
              <path d="M20 12L11 6V18L20 12Z" />
            `;
          }
        
          isForwarding = true;
        
          forwardIcon.innerHTML = `
            <rect x="6" y="4" width="5" height="16" />
            <rect x="14" y="4" width="5" height="16" />
          `;
        
          forwardInterval = setInterval(() => {
            if (currentIndex <= 0) {
              clearInterval(forwardInterval);
              forwardInterval = null;
              isForwarding = false;
        
              forwardIcon.innerHTML = `
                <path d="M11 12L20 6V18L11 12Z" />
                <path d="M4 12L13 6V18L4 12Z" />
              `;
        
              forwardBtn.disabled = true; // ✅ disattiva bottone
              return;
            }
        
            currentIndex--;
            aggiornaLightbox();
            updateNavButtons();
          }, 300);
        }

                            
        function closeLightbox() {
            document.getElementById('lightbox').classList.remove('active');
            document.getElementById('close-btn').style.display = 'none'; // 👈 nascondi
            const r = document.getElementById('rewind-btn');
            if (r) r.style.display = 'none';
            const f = document.getElementById('forward-btn');
            if (f) f.style.display = 'none';
            
            // 3) Pulisci eventuali interval in corso
              if (rewindInterval) {
                clearInterval(rewindInterval);
                rewindInterval = null;
              }
              if (forwardInterval) {
                clearInterval(forwardInterval);
                forwardInterval = null;
              }
            
              // 4) Azzeri i flag
              isRewinding  = false;
              isForwarding = false;
            
              // 5) Ripristina le icone originali solo se esistono
              const rewindIcon  = document.getElementById('rewind-icon');
              const forwardIcon = document.getElementById('forward-icon');
              if (rewindIcon) {
                rewindIcon.innerHTML = `
                  <path d="M11 12L20 6V18L11 12Z"/>
                  <path d="M4 12L13 6V18L4 12Z"/>
                `;
              }
              if (forwardIcon) {
                forwardIcon.innerHTML = `
                  <path d="M11 12L20 6V18L11 12Z"/>
                  <path d="M4 12L13 6V18L4 12Z"/>
                `;
              }
            
        }
        
        document.addEventListener("DOMContentLoaded", () => {
            const rewindBtn  = document.getElementById("rewind-btn");
              const forwardBtn = document.getElementById("forward-btn");
            
              if (rewindBtn) {
                rewindBtn.addEventListener("click", rewindToCurrent);
              }
              if (forwardBtn) {
                forwardBtn.addEventListener("click", forwardToNewest);
              }

            
            const lightbox = document.getElementById("lightbox");
        
            if (!lightbox) return;
        
            let touchStartX = 0;
            let touchEndX = 0;
        
            lightbox.addEventListener("touchstart", function (e) {
                touchStartX = e.changedTouches[0].screenX;
            });
        
            lightbox.addEventListener("touchend", function (e) {
                touchEndX = e.changedTouches[0].screenX;
                handleGesture();
            });
        
            function handleGesture() {
                const threshold = 50; // minimo in pixel per considerare uno swipe
        
                if (touchEndX < touchStartX - threshold) {
                    // swipe verso sinistra → immagine successiva
                    if (currentIndex < images.length - 1) {
                        currentIndex++;
                        aggiornaLightbox();
                        updateNavButtons();
                    }
                } else if (touchEndX > touchStartX + threshold) {
                    // swipe verso destra → immagine precedente
                    if (currentIndex > 0) {
                        currentIndex--;
                        aggiornaLightbox();
                        updateNavButtons();
                    }
                }
            }
        });
        
        
        
        // prev/next button handlers
        function prevImage(event) {
          event.stopPropagation();
          const items = window.images || [];
          if (currentIndex > 0) {
            currentIndex--;
            aggiornaLightbox();
            updateNavButtons();
          }
        }
        
        function nextImage(event) {
          event.stopPropagation();
          const items = window.images || [];
          if (currentIndex < items.length - 1) {
            currentIndex++;
            aggiornaLightbox();
            updateNavButtons();
          }
        }
            

        window.updateNavButtons = function() {
          const items     = window.images || [];
          const lastIndex = items.length - 1;
        
          // Nav prev/next (gli unici che esistono in belle.php)
          const prevNav = document.querySelector('.nav-btn.prev');
          const nextNav = document.querySelector('.nav-btn.next');
          if (prevNav) prevNav.disabled = (currentIndex === 0);
          if (nextNav) nextNav.disabled = (currentIndex === lastIndex);
        
          // Se per caso avessi ancora questi bottoni, non crasha
          const rewind  = document.getElementById('rewind-btn');
          const forward = document.getElementById('forward-btn');
          if (rewind)  rewind.disabled  = (currentIndex === lastIndex);
          if (forward) forward.disabled = (currentIndex === 0);
        
          console.log('🔄 updateNavButtons:', {
            idx: currentIndex,
            first: currentIndex === 0,
            last:  currentIndex === lastIndex,
            prevDisabled: prevNav?.disabled,
            nextDisabled: nextNav?.disabled,
            rewDisabled:  rewind?.disabled,
            fwdDisabled:  forward?.disabled
          });
        };
      
        
        document.addEventListener("DOMContentLoaded", () => {
            const rewindBtn  = document.getElementById("rewind-btn");
              const forwardBtn = document.getElementById("forward-btn");
            
              if (rewindBtn) {
                rewindBtn.addEventListener("click", rewindToCurrent);
              }
              if (forwardBtn) {
                forwardBtn.addEventListener("click", forwardToNewest);
              }
            document.querySelectorAll('.gallery img').forEach((img, index) => {
                img.addEventListener('click', () => openLightbox(index));
            });
            /* chiudi con click esterno immagine
            document.getElementById('lightbox').addEventListener('click', (e) => {
            console.log("Hai cliccato su:", e.target); // 👈 Così vedi cosa è stato cliccato
        
            if (e.target.id === 'lightbox') {
                console.log("✅ Clic sullo sfondo: chiudo lightbox");
                closeLightbox();
            } else {
                console.log("⛔ Clic su immagine o contenuto: non chiudo");
            }
        });
        */
        });