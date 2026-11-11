const API = 'http://127.0.0.1:8000';
const key = prompt('Enter the AI_API_KEY configured for the service:') || '';
const messages = document.querySelector('#messages');
const add = (who, text, sources = []) => {
  const item = document.createElement('article');
  item.className = who;
  item.textContent = text + (sources.length ? `\n\nSources: ${sources.join(', ')}` : '');
  messages.appendChild(item);
  item.scrollIntoView();
};
document.querySelectorAll('.suggestions button').forEach(button => button.onclick = () => {
  document.querySelector('#message').value = button.textContent;
  document.querySelector('#chat').requestSubmit();
});
document.querySelector('#chat').onsubmit = async event => {
  event.preventDefault();
  const input = document.querySelector('#message');
  const message = input.value.trim();
  if (!message) return;
  add('user', message); input.value = '';
  add('assistant', 'Thinking...');
  try {
    const response = await fetch(`${API}/api/chat`, { method: 'POST', headers: {'Content-Type': 'application/json', 'X-API-Key': key}, body: JSON.stringify({message}) });
    const data = await response.json();
    messages.lastElementChild.remove();
    add('assistant', response.ok ? data.answer : (data.detail || 'Request failed.'), data.sources || []);
  } catch (error) {
    messages.lastElementChild.textContent = 'Unable to reach the AI service.';
  }
};

