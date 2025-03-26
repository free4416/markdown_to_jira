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
} 