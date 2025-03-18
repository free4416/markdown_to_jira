document.addEventListener('DOMContentLoaded', function() {
    // Tab switching
    const tabs = document.querySelectorAll('.tab-button');
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.dataset.tab;
            
            // Update active tab
            document.querySelectorAll('.tab-button').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            
            // Update active content
            document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
            document.getElementById(target).classList.add('active');
        });
    });

    // Input handling
    const inputMarkdown = document.getElementById('inputMarkdown');
    const outputJira = document.getElementById('outputJira');
    const outputSlack = document.getElementById('outputSlack');
    
    let convertTimer;
    
    // 입력 텍스트 변경 시 변환
    inputMarkdown.addEventListener('input', () => {
        clearTimeout(convertTimer);
        convertTimer = setTimeout(convertMarkdown, 500);
    });

    // 라디오 버튼 변경 시 변환
    const radioButtons = document.querySelectorAll('input[name="headerStyle"]');
    radioButtons.forEach(radio => {
        radio.addEventListener('change', () => {
            if (inputMarkdown.value.trim()) {
                convertMarkdown();
            }
        });
    });

    // Conversion function
    async function convertMarkdown() {
        const markdown = inputMarkdown.value;
        if (!markdown) {
            outputJira.value = '';
            outputSlack.value = '';
            return;
        }

        const headerStyle = document.querySelector('input[name="headerStyle"]:checked').value;
        
        try {
            const response = await fetch('api/convert.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    markdown: markdown,
                    headerStyle: headerStyle
                })
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const data = await response.json();
            outputJira.value = data.jira;
            outputSlack.value = data.slack;
        } catch (error) {
            console.error('Error:', error);
            alert('Error converting markdown. Please try again.');
        }
    }

    // Copy to clipboard functionality
    function addCopyButton(textarea) {
        const button = document.createElement('button');
        button.textContent = 'Copy';
        button.className = 'copy-button';
        button.style.position = 'absolute';
        button.style.top = '10px';
        button.style.right = '10px';
        
        button.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(textarea.value);
                button.textContent = 'Copied!';
                setTimeout(() => {
                    button.textContent = 'Copy';
                }, 2000);
            } catch (err) {
                console.error('Failed to copy text:', err);
            }
        });

        textarea.parentElement.style.position = 'relative';
        textarea.parentElement.appendChild(button);
    }

    addCopyButton(outputJira);
    addCopyButton(outputSlack);
}); 