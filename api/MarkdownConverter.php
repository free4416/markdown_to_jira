<?php

class MarkdownConverter {

    private function convertHeaders($markdown, $style = 'jira') {
        if ($style === 'bold') {
            // Convert headers to *[Title]* format with asterisks
            $markdown = preg_replace('/^# (.*?)$/m', '*[$1]*', $markdown);
            $markdown = preg_replace('/^## (.*?)$/m', '*[$1]*', $markdown);
            $markdown = preg_replace('/^### (.*?)$/m', '*[$1]*', $markdown);
            $markdown = preg_replace('/^#### (.*?)$/m', '*[$1]*', $markdown);
            $markdown = preg_replace('/^##### (.*?)$/m', '*[$1]*', $markdown);
            $markdown = preg_replace('/^###### (.*?)$/m', '*[$1]*', $markdown);
        } else {
            // Convert headers to h1., h2. format
            $markdown = preg_replace('/^# (.*?)$/m', 'h1. $1', $markdown);
            $markdown = preg_replace('/^## (.*?)$/m', 'h2. $1', $markdown);
            $markdown = preg_replace('/^### (.*?)$/m', 'h3. $1', $markdown);
            $markdown = preg_replace('/^#### (.*?)$/m', 'h4. $1', $markdown);
            $markdown = preg_replace('/^##### (.*?)$/m', 'h5. $1', $markdown);
            $markdown = preg_replace('/^###### (.*?)$/m', 'h6. $1', $markdown);
        }
        return $markdown;
    }

    private function convertInlineCode($markdown) {
        return preg_replace('/`([^`]+)`/', "'$1'", $markdown);
    }

    private function convertBold($markdown) {
        return preg_replace('/\*\*(.*?)\*\*/', '*$1*', $markdown);
    }

    private function convertItalic($markdown) {
        // 이탤릭 변환을 별도로 처리하지 않음 (볼드와 충돌 방지)
        return $markdown;
    }

    private function convertLinks($markdown) {
        return preg_replace('/\[(.*?)\]\((.*?)\)/', '[$1|$2]', $markdown);
    }

    private function convertImages($markdown) {
        return preg_replace('/!\[(.*?)\]\((.*?)\)/', '!$2!', $markdown);
    }

    private function convertLists($markdown) {
        // Convert unordered lists
        $markdown = preg_replace('/^- (.*?)$/m', '* $1', $markdown);
        
        // Convert ordered lists
        $markdown = preg_replace('/^\d+\. (.*?)$/m', '# $1', $markdown);
        
        // Convert nested lists
        $markdown = preg_replace('/^  \* (.*?)$/m', '** $1', $markdown);
        $markdown = preg_replace('/^  # (.*?)$/m', '## $1', $markdown);
        
        return $markdown;
    }

    private function convertBlockquotes($markdown) {
        $lines = explode("\n", $markdown);
        $inQuote = false;
        $result = [];
        
        foreach ($lines as $line) {
            if (preg_match('/^> (.*)$/', $line, $matches)) {
                if (!$inQuote) {
                    $result[] = '{quote}' . $matches[1];
                    $inQuote = true;
                } else {
                    $result[] = $matches[1];
                }
            } else {
                if ($inQuote) {
                    $result[count($result) - 1] .= '{quote}';
                    $inQuote = false;
                }
                $result[] = $line;
            }
        }
        
        if ($inQuote) {
            $result[count($result) - 1] .= '{quote}';
        }
        
        return implode("\n", $result);
    }

    private function convertTables($markdown) {
        // 테이블 패턴 매칭
        $pattern = '/^\|(.+)\|\s*$\n^\|([-: |]+)\|\s*$\n(^\|(.+)\|\s*$\n?)+/m';
        
        return preg_replace_callback($pattern, function($matches) {
            $lines = explode("\n", $matches[0]);
            if (count($lines) < 3) {
                return $matches[0];
            }
            
            // 헤더 행 처리
            $header_cells = explode('|', trim($lines[0], '|'));
            // 공백 셀 처리 - 완전히 비어있는 대신 최소한 하나의 공백을 유지
            $header_cells = array_map(function($cell) {
                $trimmed = trim($cell);
                return $trimmed === '' ? ' ' : $trimmed;
            }, $header_cells);
            $header = '||' . implode('||', $header_cells) . '||';
            
            // 헤더 행의 셀 수를 기준으로 사용
            $expected_cell_count = count($header_cells);
            
            // 데이터 행 처리
            $data_rows = [];
            for ($i = 2; $i < count($lines); $i++) {
                if (trim($lines[$i])) {
                    $row_cells = explode('|', trim($lines[$i], '|'));
                    // 공백 셀 처리 - 완전히 비어있는 대신 최소한 하나의 공백을 유지
                    $row_cells = array_map(function($cell) {
                        $trimmed = trim($cell);
                        return $trimmed === '' ? ' ' : $trimmed;
                    }, $row_cells);
                    
                    // 셀 수가 헤더보다 적으면 빈 셀 추가
                    while (count($row_cells) < $expected_cell_count) {
                        $row_cells[] = ' ';
                    }
                    
                    $data_rows[] = '|' . implode('|', $row_cells) . '|';
                }
            }
            
            return $header . "\n" . implode("\n", $data_rows) . "\n";
        }, $markdown);
    }

    private function convertCodeBlocks($markdown) {
        // Jira에서 지원하는 언어 목록
        $supported_languages = [
            'actionscript', 'ada', 'applescript', 'bash', 'c', 'c#', 'c++', 'css',
            'erlang', 'go', 'groovy', 'haskell', 'html', 'javascript', 'json',
            'lua', 'nyan', 'objc', 'perl', 'php', 'python', 'r', 'ruby',
            'scala', 'sql', 'swift', 'visualbasic', 'xml', 'yaml', 'java'
        ];

        return preg_replace_callback('/```(.*?)\n(.*?)```/s', function($matches) use ($supported_languages) {
            $lang = strtolower(trim($matches[1]));
            $code = trim($matches[2]);
            
            if ($lang && in_array($lang, $supported_languages)) {
                return "{code:$lang}\n$code\n{code}";
            }
            return "{code}\n$code\n{code}";
        }, $markdown);
    }

    private function convertHorizontalRules($markdown) {
        // Convert markdown horizontal rules (--- or ***) to Jira horizontal rule (----)
        $markdown = preg_replace('/^(\-{3,}|\*{3,})$/m', '----', $markdown);
        return $markdown;
    }

    private function convertHtmlBr($markdown, $format = 'jira') {
        if ($format === 'jira') {
            // Jira에서는 <br> 태그를 실제 줄바꿈으로 처리
            return preg_replace('/<br\s*\/?>/i', "\n", $markdown);
        } else if ($format === 'slack') {
            // Slack에서도 <br> 태그를 실제 줄바꿈으로 처리
            return preg_replace('/<br\s*\/?>/i', "\n", $markdown);
        } else if ($format === 'plaintext') {
            // 플레인텍스트에서도 <br> 태그를 실제 줄바꿈으로 처리
            return preg_replace('/<br\s*\/?>/i', "\n", $markdown);
        }
        return $markdown;
    }

    public function toJira($markdown, $headerStyle = 'jira') {
        $result = $markdown;
        
        // Convert code blocks first to prevent interference
        $result = $this->convertCodeBlocks($result);
        
        // Convert other elements
        $result = $this->convertHeaders($result, $headerStyle);
        $result = $this->convertInlineCode($result);
        $result = $this->convertBold($result);
        $result = $this->convertItalic($result);
        $result = $this->convertLinks($result);
        $result = $this->convertImages($result);
        $result = $this->convertLists($result);
        $result = $this->convertBlockquotes($result);
        $result = $this->convertTables($result);
        $result = $this->convertHorizontalRules($result);
        $result = $this->convertHtmlBr($result, 'jira');
        
        return $result;
    }

    public function toSlack($markdown) {
        $result = $markdown;
        
        // Convert code blocks first to prevent interference
        $result = preg_replace_callback('/```(.*?)\n(.*?)```/s', function($matches) {
            $code = trim($matches[2]);
            return "```\n$code\n```";
        }, $result);
        
        // Convert inline code (preserve backticks as per Slack format)
        $result = preg_replace('/`([^`]+)`/', '`$1`', $result);
        
        // Convert headers to bold
        $result = preg_replace('/^# (.*?)$/m', '*$1*', $result);
        $result = preg_replace('/^## (.*?)$/m', '*$1*', $result);
        $result = preg_replace('/^### (.*?)$/m', '*$1*', $result);
        $result = preg_replace('/^#### (.*?)$/m', '*$1*', $result);
        $result = preg_replace('/^##### (.*?)$/m', '*$1*', $result);
        $result = preg_replace('/^###### (.*?)$/m', '*$1*', $result);
        
        // Convert bold (** to *)
        $result = preg_replace('/\*\*(.*?)\*\*/', '*$1*', $result);
        
        // Convert italic (_ to _)
        $result = preg_replace('/_(.*?)_/', '_$1_', $result);
        
        // Convert links to Slack format
        $result = preg_replace('/\[(.*?)\]\((.*?)\)/', '<$2|$1>', $result);
        
        // Convert images to just URLs
        $result = preg_replace('/!\[(.*?)\]\((.*?)\)/', '$2', $result);
        
        // Convert horizontal rules
        $result = $this->convertHorizontalRules($result);
        
        // Convert HTML <br> tags
        $result = $this->convertHtmlBr($result, 'slack');
        
        // Convert unordered lists
        $result = preg_replace('/^- (.*?)$/m', '• $1', $result);
        
        // Convert ordered lists (keep numbers)
        $result = preg_replace('/^(\d+)\. (.*?)$/m', '$1. $2', $result);
        
        // Convert blockquotes
        $result = preg_replace('/^> (.*?)$/m', '> $1', $result);
        
        // Clean up any extra newlines
        $result = preg_replace('/\n{3,}/', "\n\n", $result);
        
        return $result;
    }

    private function convertPlainTextTables($markdown) {
        // 테이블 패턴 매칭
        $pattern = '/^\|(.+)\|\s*$\n^\|([-: |]+)\|\s*$\n(^\|(.+)\|\s*$\n?)+/m';
        
        return preg_replace_callback($pattern, function($matches) {
            $lines = explode("\n", $matches[0]);
            if (count($lines) < 3) {
                return $matches[0];
            }
            
            // 헤더 추출 및 처리
            $header_cells = explode('|', trim($lines[0], '|'));
            $header_cells = array_map('trim', $header_cells);
            
            // 구분선 정보 추출 (정렬 방향 등)
            $separator_line = explode('|', trim($lines[1], '|'));
            
            // 각 열의 최대 너비 계산
            $column_widths = array_fill(0, count($header_cells), 0);
            
            // 헤더 행 셀 너비 계산
            foreach ($header_cells as $i => $cell) {
                $column_widths[$i] = max($column_widths[$i], mb_strlen(trim($cell)));
            }
            
            // 데이터 행 셀 너비 계산
            $all_rows = [];
            $all_rows[] = $header_cells; // 헤더 행 추가
            
            for ($i = 2; $i < count($lines); $i++) {
                if (trim($lines[$i])) {
                    $row_cells = explode('|', trim($lines[$i], '|'));
                    $row_cells = array_map('trim', $row_cells);
                    
                    // 셀 수 맞추기
                    while (count($row_cells) < count($header_cells)) {
                        $row_cells[] = '';
                    }
                    
                    // 열 너비 업데이트
                    foreach ($row_cells as $j => $cell) {
                        if ($j < count($column_widths)) {
                            $column_widths[$j] = max($column_widths[$j], mb_strlen($cell));
                        }
                    }
                    
                    $all_rows[] = $row_cells;
                }
            }
            
            // 테이블 문자열 조합
            $result = '';
            
            // 상단 테두리 추가
            $result .= '+';
            foreach ($column_widths as $width) {
                $result .= str_repeat('-', $width + 2) . '+';
            }
            $result .= "\n";
            
            // 헤더 행 추가
            $result .= '| ';
            foreach ($header_cells as $i => $cell) {
                $result .= $cell . str_repeat(' ', $column_widths[$i] - mb_strlen($cell)) . ' | ';
            }
            $result = rtrim($result) . "\n";
            
            // 헤더와 데이터 행 사이 구분선 추가
            $result .= '+';
            foreach ($column_widths as $width) {
                $result .= str_repeat('-', $width + 2) . '+';
            }
            $result .= "\n";
            
            // 데이터 행 추가
            for ($i = 1; $i < count($all_rows); $i++) {
                $result .= '| ';
                foreach ($all_rows[$i] as $j => $cell) {
                    $result .= $cell . str_repeat(' ', $column_widths[$j] - mb_strlen($cell)) . ' | ';
                }
                $result = rtrim($result) . "\n";
            }
            
            // 하단 테두리 추가
            $result .= '+';
            foreach ($column_widths as $width) {
                $result .= str_repeat('-', $width + 2) . '+';
            }
            $result .= "\n";
            
            return $result;
        }, $markdown);
    }

    private function convertPlainTextHeaders($markdown) {
        // 헤더를 텍스트로 변환하되 구조를 유지
        $markdown = preg_replace('/^# (.*?)$/m', "$1\n" . str_repeat('=', 50), $markdown);
        $markdown = preg_replace('/^## (.*?)$/m', "$1\n" . str_repeat('-', 40), $markdown);
        $markdown = preg_replace('/^### (.*?)$/m', "▪ $1", $markdown);
        $markdown = preg_replace('/^#### (.*?)$/m', "  ▫ $1", $markdown);
        $markdown = preg_replace('/^##### (.*?)$/m', "    ‣ $1", $markdown);
        $markdown = preg_replace('/^###### (.*?)$/m', "      • $1", $markdown);
        return $markdown;
    }

    private function convertPlainTextCodeBlocks($markdown) {
        // 코드 블록을 들여쓰기된 텍스트로 변환
        return preg_replace_callback('/```(.*?)\n(.*?)```/s', function($matches) {
            $lang = trim($matches[1]);
            $code = trim($matches[2]);
            $lines = explode("\n", $code);
            
            $result = "";
            if ($lang) {
                $result .= "[코드: " . $lang . "]\n";
            } else {
                $result .= "[코드]\n";
            }
            
            // 각 줄을 4칸 들여쓰기
            foreach ($lines as $line) {
                $result .= "    " . $line . "\n";
            }
            
            return $result;
        }, $markdown);
    }

    private function convertPlainTextInlineCode($markdown) {
        // 인라인 코드를 그대로 유지 (특수 표시 없이)
        return preg_replace('/`([^`]+)`/', '$1', $markdown);
    }

    private function convertPlainTextBold($markdown) {
        // 굵게 표시를 대문자로 변환
        return preg_replace_callback('/\*\*(.*?)\*\*/', function($matches) {
            return strtoupper($matches[1]);
        }, $markdown);
    }

    private function convertPlainTextItalic($markdown) {
        // 이탤릭을 밑줄로 강조
        return preg_replace('/_(.*?)_/', '_$1_', $markdown);
    }

    private function convertPlainTextLinks($markdown) {
        // 링크를 "텍스트 (URL)" 형식으로 변환
        return preg_replace('/\[(.*?)\]\((.*?)\)/', '$1 ($2)', $markdown);
    }

    private function convertPlainTextImages($markdown) {
        // 이미지를 "[이미지: 대체텍스트 (URL)]" 형식으로 변환
        return preg_replace('/!\[(.*?)\]\((.*?)\)/', '[이미지: $1 ($2)]', $markdown);
    }

    private function convertPlainTextLists($markdown) {
        // 순서없는 목록 (들여쓰기 유지)
        $markdown = preg_replace('/^- (.*?)$/m', '• $1', $markdown);
        $markdown = preg_replace('/^  - (.*?)$/m', '  ◦ $1', $markdown);
        $markdown = preg_replace('/^    - (.*?)$/m', '    ▪ $1', $markdown);
        
        // 순서있는 목록 (번호 유지)
        $markdown = preg_replace('/^(\d+)\. (.*?)$/m', '$1. $2', $markdown);
        
        return $markdown;
    }

    private function convertPlainTextBlockquotes($markdown) {
        // 인용구를 "> " 접두사로 변환
        $lines = explode("\n", $markdown);
        $result = [];
        
        foreach ($lines as $line) {
            if (preg_match('/^> (.*)$/', $line, $matches)) {
                $result[] = "> " . $matches[1];
            } else {
                $result[] = $line;
            }
        }
        
        return implode("\n", $result);
    }

    private function convertPlainTextHorizontalRules($markdown) {
        // 가로선을 대시 80개로 변환
        return preg_replace('/^(\-{3,}|\*{3,})$/m', str_repeat('-', 80), $markdown);
    }

    public function toPlainText($markdown) {
        $result = $markdown;
        
        // 코드 블록 먼저 변환
        $result = $this->convertPlainTextCodeBlocks($result);
        
        // 다른 요소 변환
        $result = $this->convertPlainTextHeaders($result);
        $result = $this->convertPlainTextInlineCode($result);
        $result = $this->convertPlainTextBold($result);
        $result = $this->convertPlainTextItalic($result);
        $result = $this->convertPlainTextLinks($result);
        $result = $this->convertPlainTextImages($result);
        $result = $this->convertPlainTextLists($result);
        $result = $this->convertPlainTextBlockquotes($result);
        $result = $this->convertPlainTextTables($result);
        $result = $this->convertPlainTextHorizontalRules($result);
        $result = $this->convertHtmlBr($result, 'plaintext');
        
        // 연속된 빈 줄 정리
        $result = preg_replace('/\n{3,}/', "\n\n", $result);
        
        return $result;
    }
} 