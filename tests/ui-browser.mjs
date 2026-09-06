// Offline visual/layout regression runner; only the local fixture server is used.
// Start tests/ui-router.php on 127.0.0.1:8874, Chromium CDP on 9341, then run with
// SECURITYSEARCH_UI_OUTPUT=/private/output node tests/ui-browser.mjs.
import fs from 'node:fs/promises';
import path from 'node:path';
const output = process.env.SECURITYSEARCH_UI_OUTPUT;
if (!output || !path.isAbsolute(output)) throw Error('Set an absolute SECURITYSEARCH_UI_OUTPUT path');
await fs.mkdir(output, {recursive: true});
const version = await (await fetch('http://127.0.0.1:9341/json/version')).json();
const socket = new WebSocket(version.webSocketDebuggerUrl);
await new Promise(resolve => socket.addEventListener('open', resolve, {once: true}));
let nextId = 0;
const waiting = new Map(), requests = new Map();
socket.addEventListener('message', event => {
  const message = JSON.parse(event.data);
  if (message.method === 'Network.requestWillBeSent') {
    const list = requests.get(message.sessionId) || [];
    list.push(message.params.request.url); requests.set(message.sessionId, list);
  }
  if (waiting.has(message.id)) {
    const {resolve, reject, timer} = waiting.get(message.id);
    waiting.delete(message.id); clearTimeout(timer);
    message.error ? reject(Error(message.error.message)) : resolve(message.result);
  }
});
function call(method, params = {}, sessionId) {
  const id = ++nextId;
  return new Promise((resolve, reject) => {
    const timer = setTimeout(() => {waiting.delete(id); reject(Error('Timeout: ' + method));}, 20000);
    waiting.set(id, {resolve, reject, timer});
    socket.send(JSON.stringify({id, method, params, ...(sessionId ? {sessionId} : {})}));
  });
}
const sleep = duration => new Promise(resolve => setTimeout(resolve, duration));
const report = [];
const {browserContextId} = await call('Target.createBrowserContext');
async function open(url, width, reduced = false) {
  const {targetId} = await call('Target.createTarget', {url: 'about:blank', browserContextId});
  const {sessionId} = await call('Target.attachToTarget', {targetId, flatten: true});
  await call('Page.enable', {}, sessionId);
  await call('Network.enable', {}, sessionId);
  await call('Emulation.setScriptExecutionDisabled', {value: true}, sessionId);
  await call('Emulation.setDeviceMetricsOverride', {width, height: 900, deviceScaleFactor: 1, mobile: false}, sessionId);
  await call('Emulation.setEmulatedMedia', {features: [{name: 'prefers-reduced-motion', value: reduced ? 'reduce' : 'no-preference'}]}, sessionId);
  const navigation = await call('Page.navigate', {url: 'http://127.0.0.1:8874' + url}, sessionId);
  if (navigation.errorText) throw Error(navigation.errorText);
  for (let attempt = 0; attempt < 40; attempt++) {
    const state = await evaluate(sessionId, 'document.readyState').catch(() => null);
    if (state === 'complete') break;
    await sleep(150);
  }
  await sleep(700);
  return {targetId, sessionId};
}
async function evaluate(sessionId, expression) {
  const result = await call('Runtime.evaluate', {expression, returnByValue: true}, sessionId);
  if (result.exceptionDetails) throw Error(result.exceptionDetails.text);
  return result.result.value;
}
async function screenshot(sessionId, name) {
  const {data} = await call('Page.captureScreenshot', {format: 'png', captureBeyondViewport: false}, sessionId);
  const file = path.join(output, name + '.png');
  await fs.writeFile(file, Buffer.from(data, 'base64'));
  return file;
}
try {
  for (const [name, width, reduced] of [['desktop', 1440, false], ['mobile', 390, false], ['reduced', 390, true]]) {
    const {targetId, sessionId} = await open('/', width, reduced);
    const state = await evaluate(sessionId, `({width:innerWidth,client:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth,wallpaper:getComputedStyle(document.querySelector('.ambient-wallpaper')).backgroundImage,wallpaperLayer:getComputedStyle(document.querySelector('.ambient-wallpaper')).zIndex,contentLayer:getComputedStyle(document.querySelector('#center')).zIndex,toggleDisplay:getComputedStyle(document.querySelector('.wallpaper-control')).display})`);
    const first = await screenshot(sessionId, 'lain-' + name + '-frame-a');
    await sleep(450);
    const second = await screenshot(sessionId, 'lain-' + name + '-frame-b');
    const gifRequested = (requests.get(sessionId) || []).some(url => url.includes('lain.gifv'));
    const problems = [];
    if (state.scroll > state.client) problems.push('horizontal page overflow');
    if (reduced ? (state.wallpaper !== 'none' || gifRequested) : (!state.wallpaper.includes('lain.gifv') || !gifRequested)) problems.push('wallpaper/motion preference mismatch');
    let toggleWorks = null;
    if (!reduced) {
      await evaluate(sessionId, "document.getElementById('wallpaper-static').focus()");
      await call('Input.dispatchKeyEvent', {type: 'keyDown', key: ' ', code: 'Space', windowsVirtualKeyCode: 32}, sessionId);
      await call('Input.dispatchKeyEvent', {type: 'keyUp', key: ' ', code: 'Space', windowsVirtualKeyCode: 32}, sessionId);
      toggleWorks = await evaluate(sessionId, "document.getElementById('wallpaper-static').checked && getComputedStyle(document.querySelector('.ambient-wallpaper')).backgroundImage === 'none'");
      if (!toggleWorks) problems.push('native still-wallpaper checkbox failed');
      await screenshot(sessionId, 'lain-' + name + '-paused');
    }
    const row = {test: 'home-' + name, ...state, gifRequested, toggleWorks, first, second, problems};
    report.push(row); console.log(JSON.stringify(row));
    await call('Target.closeTarget', {targetId});
  }
  for (const view of ['grid', 'compact', 'gallery', 'feed', 'list', 'filmstrip']) {
    for (const width of [320, 390, 768, 1440]) {
      const {targetId, sessionId} = await open('/fixture-images?view=' + view, width);
      const state = await evaluate(sessionId, `({client:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth,cards:document.querySelectorAll('#images > .image-wrapper').length,view:document.getElementById('images')?.className,selected:document.querySelector('select[name="view"]')?.value,overwideCards:[...document.querySelectorAll('.image-wrapper')].filter(e=>e.getBoundingClientRect().width>document.documentElement.clientWidth).length,firstRow:[...document.querySelectorAll('.image-wrapper')].slice(0,4).map(e=>({x:Math.round(e.getBoundingClientRect().x),y:Math.round(e.getBoundingClientRect().y)}))})`);
      const problems = [];
      if (state.scroll > state.client || state.overwideCards) problems.push('horizontal page/card overflow');
      if (state.cards !== 16 || state.selected !== view) problems.push('view/card mismatch');
      let keyboardScroll = null;
      if (view === 'filmstrip') {
        await evaluate(sessionId, "document.querySelector('#images .image-wrapper:last-child a').focus()");
        keyboardScroll = await evaluate(sessionId, "document.getElementById('images').scrollLeft > 0");
        if (!keyboardScroll) problems.push('filmstrip focus did not reveal final card');
        await evaluate(sessionId, "document.getElementById('images').scrollLeft=0;window.scrollTo(0,0)");
      }
      if ([390, 1440].includes(width)) await screenshot(sessionId, view + '-' + width);
      const row = {test: 'images-' + view, width, ...state, keyboardScroll, problems};
      report.push(row); console.log(JSON.stringify(row));
      await call('Target.closeTarget', {targetId});
    }
  }
} finally {
  await fs.writeFile(path.join(output, 'results.json'), JSON.stringify(report, null, 2) + '\n');
  await call('Target.disposeBrowserContext', {browserContextId});
  socket.close();
}
if (report.some(row => row.problems.length)) process.exitCode = 1;
