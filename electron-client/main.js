const { app, BrowserWindow } = require('electron');
const path = require('path');
const fs = require('fs');

const CONFIG_FILE = path.join(__dirname, 'config.json');
const DEFAULT_SERVER_URL = 'http://localhost/Colegios/public/';

function loadConfig() {
  try {
    const raw = fs.readFileSync(CONFIG_FILE, 'utf8');
    const data = JSON.parse(raw);
    if (!data.serverUrl) {
      throw new Error('La configuración no contiene "serverUrl"');
    }
    return data;
  } catch (err) {
    return { serverUrl: DEFAULT_SERVER_URL };
  }
}

let mainWindow;
let config = loadConfig();

function createWindow() {
  mainWindow = new BrowserWindow({
    width: 1280,
    height: 800,
    show: false,
    backgroundColor: '#ffffff',
    webPreferences: {
      nodeIntegration: false,
      contextIsolation: true,
      sandbox: false
    }
  });

  mainWindow.once('ready-to-show', () => {
    mainWindow.show();
  });

  mainWindow.webContents.on('did-fail-load', (_event, errorCode, errorDescription, validatedURL, isMainFrame) => {
    if (!isMainFrame) return;

    const html = `<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Error cargando servidor</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Arial, sans-serif; padding: 24px;">
  <h2 style="margin: 0 0 12px;">No se pudo cargar el servidor</h2>
  <div style="margin: 0 0 8px;"><b>URL configurada:</b> ${String(config.serverUrl).replace(/</g, '&lt;')}</div>
  <div style="margin: 0 0 8px;"><b>URL validada:</b> ${String(validatedURL).replace(/</g, '&lt;')}</div>
  <div style="margin: 0 0 8px;"><b>Código:</b> ${errorCode}</div>
  <div style="margin: 0 0 16px;"><b>Descripción:</b> ${String(errorDescription).replace(/</g, '&lt;')}</div>
  <hr style="margin: 16px 0;" />
  <div style="margin: 0 0 8px;">Verifica en el navegador que la URL abre en este equipo.</div>
  <div>Si el servidor cambió de IP, actualiza <code>electron-client/config.json</code>.</div>
</body>
</html>`;

    mainWindow.loadURL(`data:text/html;charset=utf-8,${encodeURIComponent(html)}`);
    mainWindow.webContents.openDevTools({ mode: 'detach' });
  });

  mainWindow.webContents.on('did-start-loading', () => {
    console.log('[web] did-start-loading');
  });

  mainWindow.webContents.on('did-finish-load', () => {
    console.log('[web] did-finish-load:', mainWindow.webContents.getURL());
    if (process.env.ELECTRON_DEBUG === '1') {
      mainWindow.webContents.openDevTools({ mode: 'detach' });
    }
  });

  mainWindow.webContents.on('did-redirect-navigation', (_event, url) => {
    console.log('[web] redirect ->', url);
  });

  mainWindow.webContents.on('render-process-gone', (_event, details) => {
    const html = `<!doctype html><html><body style="font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Arial, sans-serif; padding: 24px;">
      <h2 style="margin: 0 0 12px;">El proceso de renderizado se cerró</h2>
      <div style="margin: 0 0 8px;"><b>Razón:</b> ${String(details?.reason || 'desconocida').replace(/</g, '&lt;')}</div>
      <div><b>Código:</b> ${String(details?.exitCode ?? 'N/A').replace(/</g, '&lt;')}</div>
    </body></html>`;
    mainWindow.loadURL(`data:text/html;charset=utf-8,${encodeURIComponent(html)}`);
    mainWindow.webContents.openDevTools({ mode: 'detach' });
  });

  mainWindow.webContents.on('console-message', (_event, level, message, line, sourceId) => {
    console.log(`[web][${level}] ${message} (${sourceId}:${line})`);
  });

  mainWindow.loadURL(config.serverUrl).catch((err) => {
    console.error('Error en loadURL:', err);
  });

  mainWindow.on('closed', () => {
    mainWindow = null;
  });
}

app.on('ready', () => {
  config = loadConfig();
  createWindow();
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});

app.on('activate', () => {
  if (mainWindow === null) {
    createWindow();
  }
});
