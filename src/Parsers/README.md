## 注意事项<br>
目前CSS选择器只支持查找id,class,element，后面会慢慢补充。<br>
## 文档载入<br>
### 1.HTML文件(暂不支持URL)
```shell
$file = '/path/to/yourfile.ext';
$parser = HtmlParser::load($file);
```
### 2.HTML字符串
```shell
$html = '<html>....html code...</html>';
$parser = HtmlParser::load($html);
```
## 查找方式<br>
### 1.XPath
```shell
$parser = HtmlParser::load($contents);
$title = $parser->find(.//*[contains(concat(' ', normalize-space(@class), ' '),' .entry-header ')])[0]->text();
$title = $parser->xpath(.//*[contains(concat(' ', normalize-space(@class), ' '),' .entry-header ')])[0]->text();
```
### 2.CSS选择器 
```shell
$parser = HtmlParser::load($contents);
$trNodeList = $parser->find('tr');
$title = $parser->find('.entry-header')[0]->text();
$content = $parser->find('.entry')[0]->remove('.textwidget,.post-adds,.copyright-area,#author-bio,#rewardbox,.rewards')->html();
```
