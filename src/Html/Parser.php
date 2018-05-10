<?php 
/**
 * 
 */

namespace Libby\Html;

class Parser {
    
    protected $cachePattern = null;
    protected $stylesheetParser = null;
    protected $scripts = []; 
    protected $html;
    protected $pathConverterToLocal = null;
    protected $pathConverterToPublic = null;
    
    
    /**
     * 
     */
    public function getHtml ( ) {
        
        return $this->html;        
    }
    
    
    /**
     * 
     */
    public function getScriptsHash ( ) {
        
        return md5(serialize($this->scripts));
    }
    
    
    /**
     * 
     */
    public function setCachePattern ( $pattern ) {
        
        $this->cachePattern = $pattern;
    }
    
    
    /**
     * Set html context
     */    
    public function setHtml ( $html ) {
        
        $this->html = $html;
    }
    
    
    /**
     * 
     */
    public function setPathConverterToLocal ( $callback ) {
        
        $this->pathConverterToLocal = $callback;
        
        return $this;
    }
    
    
    /**
     * 
     */
    public function setPathConverterToPublic ( $callback ) {
        
        $this->pathConverterToPublic = $callback;
        
        return $this;
    }
    
    
    /**
     * 
     */
    public function setStylesheetParser ( $callback ) {
        
        $this->stylesheetParser = $callback;
    }
    
    
    /**
     * 
     */
    public function scriptsClear ( ) {
        
        $this->scripts = [];
        
        return $this;
    }
    
    
    /**
     * 
     */
    public function scriptsCombine ( ) {
        
        $stylesheet = (string) null;
        $javascript = (string) null;
        
        $pathConverterToLocal = $this->pathConverterToLocal;
        
        foreach ($this->scripts as $index => $script) {
            
            $script['path'] = $pathConverterToLocal($script['file']);
            
            if (!file_exists($script['path'])) {
                continue;
            }
            
            ${$script['type']} .= file_get_contents($script['path']) . PHP_EOL;
        }
        
        $this->scriptsClear();
        
        if (!empty($stylesheet)) {
            
            if (is_callable($callback = $this->stylesheetParser)) {
                
                $stylesheet = $callback($stylesheet);                
            }
            
            $this->scripts[] = [ 'type' => 'stylesheet', 'source' => $stylesheet, 'ext' => 'css' ];
        }
        
        if (!empty($javascript)) {
            $this->scripts[] = [ 'type' => 'javascript', 'source' => $javascript, 'ext' => 'js' ];
        }
        
        return $this;
    }
    
    
    /**
     * 
     */
    public function scriptsConvertPath ( $callback ) {
        
        foreach ($this->scripts as $index => $script) {
            
            $this->scripts[$index] = $callback($script);
        }
    }
    
    
    /**
     * 
     */
    public function scriptsExtract ( ) {
                        
        $document = new \DOMDocument;
        
        libxml_use_internal_errors(true);
        $document->loadHTML(mb_convert_encoding('<div>' . $this->html . '</div>', 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();  
        
        $xpath = new \DOMXPath($document);
        
        $elements = $xpath->query('//script');
               
        foreach ($elements as $element) {
            
            if (empty($element->getAttribute('src'))) {
                continue;    
            }
            
            $element->parentNode->removeChild($element);
            $this->scripts[] = [ 'type' => 'javascript', 'file' => $element->getAttribute('src'), 'ext' => 'js' ];
        }        
        
        $elements = $xpath->query('//link');
        
        foreach ($elements as $element) {
            $element->parentNode->removeChild($element);
            $this->scripts[] = [ 'type' => 'stylesheet', 'file' => $element->getAttribute('href'), 'ext' => 'css' ];
        }        
       
        $this->html = $document->saveHTML();
        
        
        return $this;        
    }
    
    
    /**
     * 
     */
    public function scriptsInject ( ) {
        
        foreach ($this->scripts as $script) {
                        
            switch ( $script['type'] ) {
                
                case 'stylesheet':                    
                    $this->html .= PHP_EOL . '<link rel="stylesheet" type="text/css" href="' . $script['file'] . '" />';
                break;
                
                case 'javascript':
                    $this->html .= PHP_EOL . '<script src="' . $script['file'] . '"></script>';
                break;
            }
        }
        
        return $this;
    }
    
    
    /**
     * 
     */
    public function writeCache ( ) {
        
        $pattern = $this->cachePattern;
        $pattern = str_replace('{hash}', $this->getScriptsHash(), $pattern);
        
        $pathConverterToPublic = $this->pathConverterToPublic;
        
        foreach ($this->scripts as $index => $script) {
            
            $npattern = str_replace('{type}', $script['type'], $pattern);
            $npattern = str_replace('{ext}', $script['ext'], $npattern);
            
            if (!file_exists($path = dirname($npattern))) {                
                \Libby\Dir::create($path);
            }
                        
            file_put_contents($npattern, $script['source']);
            
            $script['path'] = $npattern;
            $script['file'] = $pathConverterToPublic($script['path']);
            $this->scripts[$index] = $script;
        }
                
        return $this;
    }
}