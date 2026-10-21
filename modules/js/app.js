window.addEventListener('load', async e => { main(); init(); })

function main(e) {
    // if ('serviceWorker' in navigator){
        try{ navigator.serviceWorker.register('sw.js');
            console.log('SW regisistered');
        } catch (error) {
            console.log('SW registration failed');
        }
    // }
};
