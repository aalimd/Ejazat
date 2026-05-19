// Ejazat HR-App PWA Logic & Service Worker Registration
document.addEventListener('DOMContentLoaded', () => {
    // 1. Service Worker Registration
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            // Resolve base URL from global constant defined in header, or default to root
            const base = typeof BASE_URL !== 'undefined' ? BASE_URL : '/';
            const swUrl = `${base}sw.js?base=${encodeURIComponent(base)}`;
            
            navigator.serviceWorker.register(swUrl)
                .then((registration) => {
                    console.log('[PWA] Service Worker registered with scope:', registration.scope);
                    
                    // Listen for updates to the service worker
                    registration.addEventListener('updatefound', () => {
                        const newWorker = registration.installing;
                        if (newWorker) {
                            newWorker.addEventListener('statechange', () => {
                                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    // New content is available; prompt the user to refresh
                                    showUpdateNotification();
                                }
                            });
                        }
                    });
                })
                .catch((error) => {
                    console.error('[PWA] Service Worker registration failed:', error);
                });
        });
    }

    // 2. Custom PWA Install Prompt
    let deferredPrompt;
    const isDismissed = localStorage.getItem('pwa-dismissed-time');
    
    // Check if dismissed recently (e.g. in the last 7 days)
    const sevenDays = 7 * 24 * 60 * 60 * 1000;
    const shouldShowPrompt = !isDismissed || (Date.now() - parseInt(isDismissed)) > sevenDays;

    window.addEventListener('beforeinstallprompt', (e) => {
        // Prevent Chrome 67 and earlier from automatically showing the prompt
        e.preventDefault();
        // Stash the event so it can be triggered later.
        deferredPrompt = e;
        
        if (shouldShowPrompt) {
            // Show our custom premium install banner
            showInstallBanner();
        }
    });

    window.addEventListener('appinstalled', (evt) => {
        console.log('[PWA] App successfully installed');
        // Hide the install banner
        hideInstallBanner();
        deferredPrompt = null;
    });

    function showInstallBanner() {
        // Check if banner already exists
        if (document.getElementById('pwaInstallBanner')) return;

        const isArabic = document.documentElement.lang === 'ar';
        const base = typeof BASE_URL !== 'undefined' ? BASE_URL : '/';
        
        const texts = {
            title: isArabic ? 'تثبيت تطبيق إجازات' : 'Install Ejazat App',
            desc: isArabic 
                ? 'تصفح وإدارة إجازاتك وطلباتك بلمسة واحدة ومن شاشتك مباشرة وبدون إنترنت.' 
                : 'Manage leaves and HR requests instantly with offline support.',
            installBtn: isArabic ? 'تثبيت' : 'Install',
            dismissBtn: isArabic ? 'ليس الآن' : 'Not Now'
        };

        const banner = document.createElement('div');
        banner.id = 'pwaInstallBanner';
        banner.className = 'pwa-install-banner';
        banner.innerHTML = `
            <div class="pwa-banner-header">
                <img src="${base}assets/images/icon-192.png" alt="App Icon" class="pwa-banner-icon">
                <div class="pwa-banner-title-box">
                    <h4 class="pwa-banner-title">${texts.title}</h4>
                    <p class="pwa-banner-desc">${texts.desc}</p>
                </div>
                <button class="pwa-banner-close" aria-label="Close">&times;</button>
            </div>
            <div class="pwa-banner-actions">
                <button class="pwa-btn-dismiss">${texts.dismissBtn}</button>
                <button class="pwa-btn-install">${texts.installBtn}</button>
            </div>
        `;

        document.body.appendChild(banner);

        // Slide in animation
        setTimeout(() => {
            banner.classList.add('show');
        }, 300);

        // Add event listeners
        banner.querySelector('.pwa-btn-install').addEventListener('click', () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('[PWA] User accepted the install prompt');
                    } else {
                        console.log('[PWA] User dismissed the install prompt');
                    }
                    hideInstallBanner();
                    deferredPrompt = null;
                });
            }
        });

        const dismissAction = () => {
            localStorage.setItem('pwa-dismissed-time', Date.now());
            hideInstallBanner();
        };

        banner.querySelector('.pwa-btn-dismiss').addEventListener('click', dismissAction);
        banner.querySelector('.pwa-banner-close').addEventListener('click', dismissAction);
    }

    function hideInstallBanner() {
        const banner = document.getElementById('pwaInstallBanner');
        if (banner) {
            banner.classList.remove('show');
            setTimeout(() => {
                banner.remove();
            }, 400);
        }
    }

    function showUpdateNotification() {
        const isArabic = document.documentElement.lang === 'ar';
        const msg = isArabic 
            ? 'تحديث جديد متاح! اضغط هنا لإعادة تحميل الصفحة وتطبيقه.' 
            : 'New version available! Click here to update and reload.';
            
        const toast = document.createElement('div');
        toast.style.position = 'fixed';
        toast.style.bottom = '24px';
        toast.style.left = '24px';
        toast.style.zIndex = '99999';
        toast.style.background = '#10b981';
        toast.style.color = '#fff';
        toast.style.padding = '12px 24px';
        toast.style.borderRadius = '8px';
        toast.style.boxShadow = '0 10px 30px rgba(16, 185, 129, 0.3)';
        toast.style.cursor = 'pointer';
        toast.style.fontSize = '0.9rem';
        toast.style.fontWeight = '600';
        toast.innerText = msg;
        
        toast.addEventListener('click', () => {
            window.location.reload();
        });
        
        document.body.appendChild(toast);
    }
});
