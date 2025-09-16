document.addEventListener('DOMContentLoaded', () => {
  const body = document.body;

  const toggleBtn = document.getElementById('toggleThemeBtn');

  function updateButton() {
    if (body.classList.contains('light-theme')) {
      toggleBtn.textContent = '🌙 Mode Sombre';
    } else {
      toggleBtn.textContent = '☀️ Mode Clair';
    }
  }

  if (localStorage.getItem('theme') === 'light') {
    body.classList.add('light-theme');
  } else {
    body.classList.remove('light-theme');
  }
  updateButton();

  toggleBtn.addEventListener('click', () => {
    body.classList.toggle('light-theme');
    if (body.classList.contains('light-theme')) {
      localStorage.setItem('theme', 'light');
    } else {
      localStorage.setItem('theme', 'dark');
    }
    updateButton();
  });

  function ansiToHtml(text) {
    text = text.replace(/\x1b\[38;2;(\d+);(\d+);(\d+)m/g, (m, r, g, b) => {
      return `<span style="color: rgb(${r},${g},${b})">`;
    });
    text = text.replace(/\x1b\[0m/g, '</span>');
    text = text.replace(/\x1b\[[0-9;]*m/g, '');
    return text;
  }

  function scrollConsoleToBottom() {
    const consoleOutput = document.getElementById('consoleOutput');
    consoleOutput.scrollTop = consoleOutput.scrollHeight;
  }

  const consoleOutput = document.getElementById('consoleOutput');
  const toggleIpBtn = document.getElementById('toggleIpBtn');

  const ipsToHide = ['66.130.67.35', '172.20.0.2', 'ports 27015 SV / 27005 CL'];

  function createIpRegex(ips) {
    const escapedIps = ips.map(ip => ip.replace(/\./g, '\\.'));
    return new RegExp(escapedIps.join('|'), 'g');
  }

  const ipRegex = createIpRegex(ipsToHide);

  let ipHidden = false;
  let originalConsoleText = '';

  if (toggleIpBtn) {
    ipHidden = localStorage.getItem('ipHidden') === 'true';

    if (ipHidden) {
      toggleIpBtn.textContent = '👁️ Afficher IP';
    } else {
      toggleIpBtn.textContent = '🛡️ Masquer IP';
    }
  }

  function updateLogs() {
    fetch('logs.php')
      .then(response => response.json())
      .then(data => {
        originalConsoleText = ansiToHtml(data.logs);

        const scrollPos = consoleOutput.scrollTop;
        const scrollHeightBefore = consoleOutput.scrollHeight;

        if (ipHidden) {
          consoleOutput.innerHTML = originalConsoleText.replace(ipRegex, '[Information offusquer]');
        } else {
          consoleOutput.innerHTML = originalConsoleText;
        }

        const scrollHeightAfter = consoleOutput.scrollHeight;

        consoleOutput.scrollTop = scrollPos + (scrollHeightAfter - scrollHeightBefore);
      })
      .catch(err => {
        consoleOutput.textContent = 'Erreur lors du chargement des logs: ' + err;
      });
  }

  setInterval(updateLogs, 2000);
  updateLogs();

  if (toggleIpBtn) {
    toggleIpBtn.addEventListener('click', () => {
      ipHidden = !ipHidden;
      localStorage.setItem('ipHidden', ipHidden);

      if (ipHidden) {
        consoleOutput.innerHTML = originalConsoleText.replace(ipRegex, '[Information offusquer]');
        toggleIpBtn.textContent = '👁️ Afficher IP';
      } else {
        consoleOutput.innerHTML = originalConsoleText;
        toggleIpBtn.textContent = '🛡️ Masquer IP';
      }
    });
  }

  document.getElementById('cmdSend').addEventListener('click', function () {
    const input = document.getElementById('cmdInput');
    const command = input.value.trim();
    if (!command) return;

    fetch('rcon.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ command }),
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          originalConsoleText += `\n&gt; ${command}\n${data.output}`;
        } else {
          originalConsoleText += `\n[Erreur] ${data.message}`;
        }

        if (ipHidden) {
          consoleOutput.innerHTML = originalConsoleText.replace(ipRegex, '[Information offusquer]');
        } else {
          consoleOutput.innerHTML = originalConsoleText;
        }
      })
      .catch((err) => {
        originalConsoleText += `\n[Erreur réseau] ${err}`;
        if (ipHidden) {
          consoleOutput.innerHTML = originalConsoleText.replace(ipRegex, '[IP masquée]');
        } else {
          consoleOutput.innerHTML = originalConsoleText;
        }
      });

    input.value = '';
  });

  const editingFile = body.getAttribute('data-editing-file');
  const fileContentEncoded = body.getAttribute('data-file-content');
  const fileLanguage = body.getAttribute('data-file-language');

  if (editingFile && fileContentEncoded && fileLanguage) {
    const fileContent = atob(fileContentEncoded);

    require.config({ paths: { vs: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.43.0/min/vs' } });
    require(['vs/editor/editor.main'], function () {
      window.editor = monaco.editor.create(document.getElementById('editor'), {
        value: fileContent,
        language: fileLanguage,
        theme: 'vs-dark',
        automaticLayout: true,
      });
    });

    document.getElementById('saveBtn').addEventListener('click', function () {
      const content = window.editor.getValue();
      const formData = new FormData();
      formData.append('file_content', content);
      formData.append('file_path', editingFile);
      formData.append('save_file', '1');

      fetch('', {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
        },
      })
        .then((resp) => resp.json())
        .then((data) => {
          alert(data.message || 'Fichier sauvegardé');
        })
        .catch((err) => alert('Erreur : ' + err));
    });

    document.getElementById('closeEditorBtn').addEventListener('click', function () {
      const url = new URL(window.location);
      url.searchParams.delete('edit');
      window.location.href = url.toString();
    });
  }

  document.querySelectorAll('button[name="install"]').forEach(btn => {
    const form = btn.closest('form');

    if (btn.dataset.installed === "true") {
      btn.addEventListener('click', (e) => e.preventDefault()); // empêcher le submit
      btn.addEventListener('dblclick', () => {
        const gamemode = btn.value;
        document.getElementById('deleteGamemodeInput').value = gamemode;
        document.getElementById('deleteModalText').textContent = `Supprimer définitivement le gamemode "${gamemode}" ?`;
        document.getElementById('deleteModal').style.display = 'flex';
      });
      btn.classList.add('fake-disabled');
    } else {
      form.addEventListener('submit', (e) => {
        btn.classList.add('loading');
      });
    }
  });
  document.querySelectorAll('button[name="install"]').forEach(btn => {
    const isInstalled = btn.dataset.installed === "true";
    if (isInstalled) {
        btn.addEventListener('click', (e) => e.preventDefault());
        btn.addEventListener('dblclick', () => {
            const gamemode = btn.value;
            document.getElementById('deleteGamemodeInput').value = gamemode;
            document.getElementById('deleteModalText').textContent = `Supprimer définitivement le gamemode "${gamemode}" ?`;
            document.getElementById('deleteModal').style.display = 'flex';
        });
        btn.classList.add('fake-disabled');
    } else {
        btn.closest('form').addEventListener('submit', () => {
            document.getElementById('loadingOverlay').style.display = 'flex';
        });
    }
});
function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}
const stopBtn = document.querySelector('button[value="stop"]');
stopBtn?.addEventListener('click', (e) => {
    document.getElementById('loadingOverlay').style.display = 'flex';
    const interval = setInterval(() => {
        const consoleText = document.getElementById('consoleOutput').innerText;
        if (consoleText.includes('Serveur fermé')) {
            clearInterval(interval);
            document.getElementById('loadingOverlay').style.display = 'none';
        }
    }, 1000);
});
const btnStart = document.getElementById('btnStart');
const btnStop = document.getElementById('btnStop');
function updateServerButtons() {
    const text = document.getElementById('consoleOutput').innerText;
    if (text.includes('Serveur fermé')) {
        btnStart.style.display = 'inline-block';
        btnStop.style.display = 'none';
    } else {
        btnStart.style.display = 'none';
        btnStop.style.display = 'inline-block';
    }
}
setInterval(updateServerButtons, 100);
window.addEventListener('DOMContentLoaded', updateServerButtons);

});

