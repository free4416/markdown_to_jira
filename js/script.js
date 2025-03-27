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
    const outputPlaintext = document.getElementById('outputPlaintext');
    
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
            outputPlaintext.value = '';
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
            outputPlaintext.value = data.plaintext;
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

    // Add edit button functionality
    function addEditButton(textarea) {
        // 수정 버튼 생성
        const editButton = document.createElement('button');
        editButton.textContent = 'Edit';
        editButton.className = 'edit-button';
        editButton.style.position = 'absolute';
        editButton.style.top = '10px';
        editButton.style.right = '70px'; // Copy 버튼 왼쪽에 배치
        
        // 수정 버튼 클릭 이벤트
        editButton.addEventListener('click', () => {
            // 모달 오버레이 생성
            const overlay = document.createElement('div');
            overlay.className = 'modal-overlay';
            
            // 모달 컨테이너 생성
            const modal = document.createElement('div');
            modal.className = 'edit-modal';
            
            // 모달 헤더 생성
            const modalHeader = document.createElement('div');
            modalHeader.className = 'modal-header';
            
            const modalTitle = document.createElement('h3');
            modalTitle.textContent = 'Edit Content';
            
            const closeButton = document.createElement('button');
            closeButton.textContent = '×';
            closeButton.className = 'close-button';
            
            modalHeader.appendChild(modalTitle);
            modalHeader.appendChild(closeButton);
            
            // 모달 본문 생성
            const modalBody = document.createElement('div');
            modalBody.className = 'modal-body';
            
            // 수정 가능한 텍스트 영역 생성
            const editTextarea = document.createElement('textarea');
            editTextarea.value = textarea.value; // 원본 텍스트 복사
            editTextarea.className = 'edit-textarea';
            
            modalBody.appendChild(editTextarea);
            
            // 모달 푸터 생성
            const modalFooter = document.createElement('div');
            modalFooter.className = 'modal-footer';
            
            // 복사 버튼 생성
            const copyButton = document.createElement('button');
            copyButton.textContent = 'Copy';
            copyButton.className = 'copy-button-modal';
            
            modalFooter.appendChild(copyButton);
            
            // 모달 조립
            modal.appendChild(modalHeader);
            modal.appendChild(modalBody);
            modal.appendChild(modalFooter);
            overlay.appendChild(modal);
            
            // body에 모달 추가
            document.body.appendChild(overlay);
            
            // 포커스 설정
            editTextarea.focus();
            
            // 닫기 버튼 이벤트
            closeButton.addEventListener('click', () => {
                document.body.removeChild(overlay);
            });
            
            // 오버레이 클릭 시 닫기
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    document.body.removeChild(overlay);
                }
            });
            
            // 복사 버튼 이벤트
            copyButton.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(editTextarea.value);
                    copyButton.textContent = 'Copied!';
                    setTimeout(() => {
                        copyButton.textContent = 'Copy';
                    }, 2000);
                } catch (err) {
                    console.error('Failed to copy text:', err);
                }
            });
            
            // ESC 키 누르면 모달 닫기
            document.addEventListener('keydown', function escListener(e) {
                if (e.key === 'Escape') {
                    document.body.removeChild(overlay);
                    document.removeEventListener('keydown', escListener);
                }
            });
        });
        
        // 버튼을 부모 요소에 추가
        textarea.parentElement.appendChild(editButton);
    }

    addCopyButton(outputJira);
    addCopyButton(outputSlack);
    addCopyButton(outputPlaintext);
    
    addEditButton(outputJira);
    addEditButton(outputSlack);
    addEditButton(outputPlaintext);
}); 