### 代码示例
```shell
    require_once(__DIR__ . '../../../vendor/autoload.php');
    
    use PHPSpider\Component\Mysql;
    use PHPSpider\Component\Mysql\Builder;
    
    $config = [
        'host'            => '',
        'port'            => '',
        'dbname'          => '',
        'username'        => '',
        'password'        => '',
        'charset'         => 'utf8',
        'prefix'          => 'blog_',
    ];
        
    $where  = ['id'=>1];
    $where1 = ['id'=>['>',1]];
    $where3 = 'id=1';
    
    $data = ['name' => '12360'];
    
    $multiData = [
        ['name' => '百度'],
        ['name' => '谷歌']
    ];
    
    $db = Mysql::instance($config);
    $result = $db->startTrans();
    $result = $db->rollback();
    $result = $db->commit();
    $result = $db->showTables('dinghong'); //$db->showTables()
    $result = $db->showColumns('dinghong.dh_log'); //$db->showColumns('blog_links')
    $result = $db->explain("select * from blog_links");
    #插入
    $result = $db->table('__test__')->fetchSql()->insertAll($multiData);
    $result = $db->table('__test__')->insertAll($multiData);
    $result = $db->table('__links__')->field('title')->fetchSql()->where('id=8')->insertSelect('name','__test__');
    $result = $db->table('__links__')->field('title')->where('id=8')->insertSelect('name','__test__');
    $result = $db->table('__test__')->fetchSql()->data($data)->insert();
    $result = $db->table('__test__')->data($data)->insert();
    $result = $db->table('__test__')->data($data)->insertGetId();
    #删除
    $result = $db->fetchSql()->table('__test__')->where('id=4')->delete();
    $result = $db->table('__test__')->where('id=4')->delete();
    #修改
    $result = $db->fetchSql()->table('__test__')->where('id=24')->data($data)->update();
    $result = $db->table('__test__')->where('id=24')->data($data)->update();
    $result = $db->fetchSql()->table('__test__')->where('id=4')->update($data);
    $result = $db->table('__test__')->where('id=24')->update($data);
    $result = $db->table('__test__')->where('id=24')->increase('level',1);
    $result = $db->table('__test__')->where('id=24')->decrease('level',1);
    #查询
    $result = $db->fetchSql(false)->table('__test__')->select();
    $result = $db->table('__test__')->select();
    $result = $db->fetchSql()->table('__test__')->find();
    $result = $db->table('__test__')->find();
    $result = $db->fetchSql()->table('__test__')->value('name');
    $result = $db->table('__test__')->value('name');
    $result = $db->fetchSql()->table('__test__')->column('name');
    $result = $db->table('__test__')->column('name');
    $result = $db->table('__test__')->column('name','id');
    $result = $db->fetchSql()->table('test')->count();
    $result = $db->table('__test__')->count();
    $result = $db->fetchSql(false)->table('__test__')->sum('level');
    $result = $db->fetchSql(false)->table('__test__')->min('level');
    $result = $db->fetchSql(false)->table('__test__')->max('level');
    $result = $db->fetchSql(false)->table('__test__')->avg('level');
    #使用where条件进行查询
    $result = $db->fetchSql(true)->table('__test__')->where($where)->select();
    $result = $db->fetchSql(true)->table('__test__')->where($where1)->select();
    $result = $db->fetchSql(true)->table('__test__')->where($where3)->select();
    #使用distinct
    $result = $db->fetchSql(false)->distinct(true)->table('__test__')->select();
    #使用innerJoin
    $result = $db->fetchSql(false)->field('t.name,l.title')->table('__test__ t')->innerJoin('__links__ l','t.id=l.id')->select();
    #使用leftJoin
    $result = $db->fetchSql(false)->field('t.name,l.title')->table('__test__ t')->leftJoin('__links__ l','t.id=l.id')->select();
    #使用rightJoin
    $result = $db->fetchSql(false)->field('t.name,l.title')->table('__test__ t')->rightJoin('__links__ l','t.id=l.id')->select();
    #使用group
    $result = $db->fetchSql(false)->table('__test__')->group('level')->select();
    #使用having
    $result = $db->fetchSql(true)->table('__test__')->group('level')->having('level > 0')->select();
    #使用order
    $result = $db->fetchSql(false)->table('__test__')->order('id desc')->select();
    #使用limit
    $result = $db->fetchSql(false)->table('__test__')->order('id desc')->limit('0,2')->select();
    #多个连贯操作同时使用
    $result = $db->fetchExplain(true)->fetchSql(false)->table('__test__')->group('id')->having('id>0')->order('id desc')->limit('0,2')->select();
```
