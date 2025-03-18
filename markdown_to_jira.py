import re
import tkinter as tk
from tkinter import messagebox
import pyperclip
 
def convert_markdown_to_jira(use_star_title=False):
    # 클립보드에서 마크다운 텍스트 가져오기
    try:
        markdown_text = pyperclip.paste()
        if not markdown_text:
            messagebox.showwarning("경고", "클립보드가 비어 있습니다!")
            return
    except Exception as e:
        messagebox.showerror("오류", f"클립보드 읽기 오류: {str(e)}")
        return
     
    # 변환 전의 원본 텍스트 저장
    original_text = markdown_text
     
    # 코드 블록 저장 (변환 중에 보호하기 위해)
    code_blocks = []
    def save_code_block(match):
        lang = match.group(1) or ''
        code = match.group(2)
        code_blocks.append((lang.strip(), code))
        return f"__CODE_BLOCK_{len(code_blocks)-1}__"
     
    # 코드 블록 임시 저장 (```로 시작하는 코드 블록)
    markdown_text = re.sub(r'```(.*?)\n(.*?)```', save_code_block, markdown_text, flags=re.DOTALL)
     
    # 헤더 변환 (Jira 형식 또는 *[타이틀]* 형식)
    if use_star_title:
        # *[타이틀]* 형식으로 변환 - 줄바꿈 제거
        markdown_text = re.sub(r'^# (.*?)$', lambda m: '*[' + m.group(1).strip() + ']*', markdown_text, flags=re.MULTILINE)
        markdown_text = re.sub(r'^## (.*?)$', lambda m: '*[' + m.group(1).strip() + ']*', markdown_text, flags=re.MULTILINE)
        markdown_text = re.sub(r'^### (.*?)$', lambda m: '*[' + m.group(1).strip() + ']*', markdown_text, flags=re.MULTILINE)
        markdown_text = re.sub(r'^#### (.*?)$', lambda m: '*[' + m.group(1).strip() + ']*', markdown_text, flags=re.MULTILINE)
        markdown_text = re.sub(r'^##### (.*?)$', lambda m: '*[' + m.group(1).strip() + ']*', markdown_text, flags=re.MULTILINE)
        markdown_text = re.sub(r'^###### (.*?)$', lambda m: '*[' + m.group(1).strip() + ']*', markdown_text, flags=re.MULTILINE)
    else:
        # 기존 Jira 형식으로 변환 - 줄바꿈 제거
        markdown_text = re.sub(r'^# (.*?)$', lambda m: 'h1. ' + m.group(1).strip(), markdown_text, flags=re.MULTILINE)
        markdown_text = re.sub(r'^## (.*?)$', lambda m: 'h2. ' + m.group(1).strip(), markdown_text, flags=re.MULTILINE)
        markdown_text = re.sub(r'^### (.*?)$', lambda m: 'h3. ' + m.group(1).strip(), markdown_text, flags=re.MULTILINE)
        markdown_text = re.sub(r'^#### (.*?)$', lambda m: 'h4. ' + m.group(1).strip(), markdown_text, flags=re.MULTILINE)
        markdown_text = re.sub(r'^##### (.*?)$', lambda m: 'h5. ' + m.group(1).strip(), markdown_text, flags=re.MULTILINE)
        markdown_text = re.sub(r'^###### (.*?)$', lambda m: 'h6. ' + m.group(1).strip(), markdown_text, flags=re.MULTILINE)
     
    # 인라인 코드 (`code` -> ''code'')
    markdown_text = re.sub(r'`([^`]+)`', r"'\1'", markdown_text)
     
    # 굵게 (**text** -> *text*)
    markdown_text = re.sub(r'\*\*(.*?)\*\*', r'*\1*', markdown_text)
     
    # 이탤릭릭 (" *text* " -> " _text_ ") - 앞뒤에 공백이 있는 이탤릭 변환 (볼드문법과 중첩되어 회피하기 위해 이렇게 처리함, 이탤릭 잘 안씀 ㅎ)
    # 볼드체와 중첩되어 이슈가 많이 발생하여 주석처리리
    # markdown_text = re.sub(r'(\s)\*(.*?)\*(\s)', r'\1_\2_\3', markdown_text)
     
    # 링크 ([text](url) -> [text|url])
    markdown_text = re.sub(r'\[(.*?)\]\((.*?)\)', r'[\1|\2]', markdown_text)
     
    # 이미지 (![alt](url) -> !url!)
    markdown_text = re.sub(r'!\[(.*?)\]\((.*?)\)', r'!\2!', markdown_text)
     
    # 순서 없는 목록 (- item -> * item)
    markdown_text = re.sub(r'^- (.*?)$', r'* \1', markdown_text, flags=re.MULTILINE)
     
    # 들여쓰기된 순서 없는 목록 처리 (   - item ->    #- item)
    markdown_text = re.sub(r'^(\s{3,})- (.*?)$', r'\1#- \2', markdown_text, flags=re.MULTILINE)
     
    # 순서 있는 목록 (1. item -> # item)
    markdown_text = re.sub(r'^\d+\. (.*?)$', r'# \1', markdown_text, flags=re.MULTILINE)
     
    # 중첩된 목록 처리
    markdown_text = re.sub(r'^  \* (.*?)$', r'** \1', markdown_text, flags=re.MULTILINE)
    markdown_text = re.sub(r'^  # (.*?)$', r'## \1', markdown_text, flags=re.MULTILINE)
     
    # 인용문 (> text -> {quote}text{quote})
    # 연속된 인용문 처리
    in_quote = False
    lines = markdown_text.split('\n')
    for i in range(len(lines)):
        if lines[i].startswith('> '):
            if not in_quote:
                lines[i] = '{quote}' + lines[i][2:]
                in_quote = True
            else:
                lines[i] = lines[i][2:]
        elif in_quote:
            lines[i-1] += '{quote}'
            in_quote = False
     
    if in_quote:
        lines[-1] += '{quote}'
     
    markdown_text = '\n'.join(lines)
     
    # 테이블 변환
    table_pattern = re.compile(r'^\|(.+)\|\s*$\n^\|([-: |]+)\|\s*$\n(^\|(.+)\|\s*$\n?)+', re.MULTILINE)
 
    def convert_table(match):
        lines = match.group(0).strip().split('\n')
        if len(lines) < 3:
            return match.group(0)
         
        # 첫 번째 행을 헤더로 변환
        header_cells = re.findall(r'\|(.*?)(?=\|)', lines[0] + '|')
        header_cells = [cell.strip() for cell in header_cells if cell.strip()]
        header = '||' + '||'.join(header_cells) + '||'
         
        # 나머지 데이터 행 처리
        data_rows = []
        for i in range(2, len(lines)):
            if lines[i].strip():
                # 각 셀의 내용 추출하고 공백 유지하되 앞뒤 공백만 제거
                row_cells = re.findall(r'\|(.*?)(?=\|)', lines[i])
                row_cells = [cell.strip() for cell in row_cells if cell != None]
                data_rows.append('|' + '|'.join(row_cells) + '|')
         
        # 결과 조합
        result = header + '\n' + '\n'.join(data_rows) + '\n'
        return result
 
    # 테이블 찾아서 변환
    markdown_text = re.sub(table_pattern, convert_table, markdown_text)
     
    # 수평선 (--- -> ----)
    markdown_text = re.sub(r'^---+$', r'----', markdown_text, flags=re.MULTILINE)
     
    # 코드 블록 복원
    for i, (lang, code) in enumerate(code_blocks):
        # Jira에서 지원하는 언어 목록
        supported_languages = {
            'actionscript', 'ada', 'applescript', 'bash', 'c', 'c#', 'c++', 'css',
            'erlang', 'go', 'groovy', 'haskell', 'html', 'javascript', 'json',
            'lua', 'nyan', 'objc', 'perl', 'php', 'python', 'r', 'ruby',
            'scala', 'sql', 'swift', 'visualbasic', 'xml', 'yaml', 'java'
        }
         
        # 언어가 지정되었고 지원하는 언어인 경우에만 언어 지정
        if lang and lang.lower() in supported_languages:
            replacement = f"{{code:{lang.lower()}}}\n{code.rstrip()}\n{{code}}"
        else:
            replacement = f"{{code}}\n{code.rstrip()}\n{{code}}"
             
        markdown_text = markdown_text.replace(f"__CODE_BLOCK_{i}__", replacement)
     
    # 목록 항목 사이의 빈 줄 제거
    lines = markdown_text.split('\n')
    result_lines = []
     
    i = 0
    while i < len(lines):
        result_lines.append(lines[i])
         
        # 현재 줄이 목록 항목인지 확인 (* 또는 # 또는 #-로 시작하는 줄)
        current_is_list_item = bool(re.match(r'^\s*(\*|#|#-)', lines[i]))
         
        if current_is_list_item and i + 1 < len(lines):
            # 현재 줄이 목록 항목이고 다음 줄이 빈 줄이고 그 다음 줄도 목록 항목이면 빈 줄 건너뛰기
            if lines[i + 1].strip() == "" and i + 2 < len(lines) and bool(re.match(r'^\s*(\*|#|#-)', lines[i + 2])):
                i += 2  # 빈 줄 건너뛰기
            else:
                i += 1
        else:
            i += 1
     
    markdown_text = '\n'.join(result_lines)
     
    # 변환된 텍스트를 클립보드에 복사
    try:
        pyperclip.copy(markdown_text)
        messagebox.showinfo("변환 완료", "마크다운이 Jira 형식으로 변환되어 클립보드에 복사되었습니다!")
    except Exception as e:
        messagebox.showerror("오류", f"클립보드 쓰기 오류: {str(e)}")
        return
 
# GUI 생성
def create_gui():
    root = tk.Tk()
    root.title("마크다운 → Jira 변환기")
    
    # 윈도우 크기 및 위치 설정
    window_width = 450
    window_height = 280  # 높이를 더 늘림
    screen_width = root.winfo_screenwidth()
    screen_height = root.winfo_screenheight()
    x_position = (screen_width - window_width) // 2
    y_position = (screen_height - window_height) // 2
    root.geometry(f"{window_width}x{window_height}+{x_position}+{y_position}")
    
    # 상단 설명 레이블
    label = tk.Label(root, text="클립보드의 마크다운을 Jira 형식으로 변환합니다.")
    label.pack(pady=(20, 10))
    
    # 헤딩 스타일 옵션 프레임
    style_frame = tk.Frame(root)
    style_frame.pack(pady=(0, 10))
    
    # 헤딩 스타일 선택 라디오 버튼
    heading_style = tk.IntVar()
    heading_style.set(0)  # 기본값: Jira 스타일
    
    style_label = tk.Label(style_frame, text="헤더 스타일 선택:")
    style_label.grid(row=0, column=0, padx=(0, 10), sticky="w")
    
    jira_style_radio = tk.Radiobutton(style_frame, text="Head 스타일 (h1., h2.)", variable=heading_style, value=0)
    jira_style_radio.grid(row=0, column=1, padx=(0, 10), sticky="w")
    
    star_style_radio = tk.Radiobutton(style_frame, text="Bold 스타일 (*[타이틀]*)", variable=heading_style, value=1)
    star_style_radio.grid(row=0, column=2, sticky="w")
    
    # 변환 버튼
    convert_button = tk.Button(root, text="클립보드 마크다운 변환하기", 
                               command=lambda: convert_markdown_to_jira(heading_style.get() == 1),
                               height=2, width=30, bg="#4CAF50", fg="white")
    convert_button.pack(pady=10)
    
    # 단축키 안내 레이블
    shortcut_label = tk.Label(root, text="Ctrl+C로 변환할 텍스트를 클립보드에 복사하고\n변환 버튼을 클릭하세요.")
    shortcut_label.pack(pady=(5, 10))
    
    # 개발자 정보 레이블
    developer_label = tk.Label(root, text="개발자: wjsong\n버그 리포트: wjsong", fg="#666666", font=("Arial", 8))
    developer_label.pack(pady=(0, 10))
    
    # 윈도우 항상 위에 표시
    root.attributes('-topmost', False)
     
    root.mainloop()
 
if __name__ == "__main__":
    create_gui()