@if(config('services.firebase.project_id') && config('services.firebase.client_email') && config('services.firebase.private_key') && config('services.firebase.web.api_key') && config('services.firebase.web.vapid_key'))
@php
    $firebaseClientConfig = [
        'apiKey' => config('services.firebase.web.api_key'),
        'authDomain' => config('services.firebase.web.auth_domain'),
        'projectId' => config('services.firebase.web.project_id'),
        'storageBucket' => config('services.firebase.web.storage_bucket'),
        'messagingSenderId' => config('services.firebase.web.messaging_sender_id'),
        'appId' => config('services.firebase.web.app_id'),
    ];
@endphp

<section class="customer-push-settings" aria-labelledby="customer-push-title">
    <div class="customer-push-settings__content">
        <strong id="customer-push-title">Уведомления о заказах</strong>
        <p id="customer-push-status">Разрешите push-уведомления, чтобы узнавать об изменении статуса заказа.</p>
    </div>
    <button type="button" id="customer-push-toggle" class="customer-push-settings__button">Включить уведомления</button>
</section>
<style>
.customer-push-settings{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:0 0 18px;padding:16px 18px;color:#f4f4f4;background:linear-gradient(145deg,#0b0c18,#14182a 58%,#0b0c18);border:1px solid rgba(0,229,212,.42);border-radius:15px;box-shadow:0 8px 24px rgba(0,0,0,.24)}
.customer-push-settings__content strong{color:#ffd700}
.customer-push-settings__content p{margin:5px 0 0;color:#aeb5c4;font-size:.88rem}
.customer-push-settings__button{flex:0 0 auto;min-height:42px;padding:9px 16px;color:#00e5d4;background:transparent;border:1px solid #00e5d4;border-radius:22px;font-weight:700;cursor:pointer}
.customer-push-settings__button:hover{color:#0b0c18;background:#00e5d4}
.customer-push-settings__button:disabled{opacity:.55;cursor:wait}
@media(max-width:620px){.customer-push-settings{align-items:stretch;flex-direction:column}}
</style>
@push('scripts')
<script src="https://www.gstatic.com/firebasejs/10.13.2/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.13.2/firebase-messaging-compat.js"></script>
<script>
(function(){
    'use strict';
    const button=document.getElementById('customer-push-toggle');
    const status=document.getElementById('customer-push-status');
    if(!button||!status)return;
    const firebaseConfig=@json($firebaseClientConfig);
    const vapidKey=@json(config('services.firebase.web.vapid_key'));
    const storeUrl=@json(route('customer.push.devices.store'));
    const deleteUrl=@json(route('customer.push.devices.destroy'));
    const csrfToken=document.querySelector('meta[name="csrf-token"]')?.content||'';
    let messaging=null,currentToken=null,enabled=false,foregroundHandlerRegistered=false;
    function setStatus(message){status.textContent=message;}

    // Общий звуковой обработчик предотвращает двойной сигнал,
    // если один статус одновременно пришёл через Firebase и опрос заказа.
    window.playDaodesCustomerNotificationSound = window.playDaodesCustomerNotificationSound || function(){
        const now = Date.now();
        if (window.__daodesCustomerNotificationSoundAt && now - window.__daodesCustomerNotificationSoundAt < 2000) return;
        window.__daodesCustomerNotificationSoundAt = now;

        try {
            const audio = new Audio('/sounds/notification.mp3');
            audio.preload = 'auto';
            audio.play().catch(function(error){
                console.debug('Customer notification sound was blocked:', error);
            });
        } catch(error) {
            console.warn('Customer notification sound:', error);
        }
    };

    async function initializeMessaging(){
        if(!('serviceWorker'in navigator)||!('Notification'in window))throw new Error('Этот браузер не поддерживает push-уведомления.');
        if(!(await firebase.messaging.isSupported()))throw new Error('Push-уведомления не поддерживаются в этом браузере.');
        if(!firebase.apps.length)firebase.initializeApp(firebaseConfig);
        messaging=firebase.messaging();
        return navigator.serviceWorker.register('/firebase-messaging-sw.js');
    }
    async function saveToken(token){
        const response=await fetch(storeUrl,{method:'POST',credentials:'same-origin',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrfToken},body:JSON.stringify({token:token,platform:'web',device_name:navigator.userAgent.slice(0,120)})});
        if(!response.ok)throw new Error('Не удалось сохранить подписку на сервере.');
    }
    async function enableNotifications(){
        if(Notification.permission!=='granted'){
            const permission=await Notification.requestPermission();
            if(permission!=='granted'){setStatus('Разрешение на уведомления не предоставлено.');return;}
        }
        const registration=await initializeMessaging();
        currentToken=await messaging.getToken({vapidKey:vapidKey,serviceWorkerRegistration:registration});
        if(!currentToken)throw new Error('Браузер не выдал push-токен.');
        await saveToken(currentToken);
        enabled=true;
        button.textContent='Отключить уведомления';
        setStatus('Уведомления включены на этом устройстве.');
        if(!foregroundHandlerRegistered){
            messaging.onMessage(function(payload){
                const notification=payload.notification||{};

                // Звук для push, полученного при открытой странице клиента.
                window.playDaodesCustomerNotificationSound();

                if(Notification.permission==='granted'){
                    const browserNotification=new Notification(notification.title||'DAODES',{body:notification.body||'',icon:'/pwa-icon.svg',data:{url:payload.data?.url||'/customer/orders'}});
                    browserNotification.onclick=function(){window.focus();window.location.href=browserNotification.data.url;};
                }
            });
            foregroundHandlerRegistered=true;
        }
    }
    async function disableNotifications(){
        if(!messaging)await initializeMessaging();
        if(!currentToken){
            const registration=await navigator.serviceWorker.ready;
            currentToken=await messaging.getToken({vapidKey:vapidKey,serviceWorkerRegistration:registration});
        }
        if(currentToken){
            await fetch(deleteUrl,{method:'DELETE',credentials:'same-origin',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrfToken},body:JSON.stringify({token:currentToken})});
            await messaging.deleteToken();
        }
        currentToken=null;enabled=false;
        button.textContent='Включить уведомления';
        setStatus('Уведомления отключены на этом устройстве.');
    }
    button.addEventListener('click',async function(){
        button.disabled=true;
        try{if(enabled)await disableNotifications();else await enableNotifications();}
        catch(error){setStatus(error.message||'Не удалось настроить push-уведомления.');}
        finally{button.disabled=false;}
    });
    if(Notification.permission==='granted'){
        enableNotifications().catch(error=>setStatus(error.message||'Не удалось восстановить push-подписку.'));
    }
})();
</script>
@endpush
@endif
