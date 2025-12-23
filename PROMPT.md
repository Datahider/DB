# Библиотека losthost/db - ORM-подобная обертка над PDO для MySQL

## 📋 Общее описание

Библиотека `losthost/db` предоставляет многоуровневую абстракцию для работы с MySQL через PDO. Основные цели:
- Минимальный boilerplate код
- Автоматические миграции схемы
- Безопасность (prepared statements)
- Удобная работа с объектами и коллекциями

**Архитектура (4 слоя):**
1. **`DB`** - низкоуровневая обертка (соединение, транзакции, блокировки)
2. **`DBObject`** - Active Record для таблиц
3. **`DBList`** - Repository для коллекций (lazy loading)
4. **`DBView` / `DBValue`** - произвольные запросы и скалярные значения

## 🚀 Быстрый старт

### Настройка соединения (bootstrap.php)
```php
use losthost\DB\DB;

define('DB_HOST', 'localhost');
define('DB_USER', 'your_user');
define('DB_NAME', 'your_db');
define('DB_PREF', 'prefix_'); // Префикс таблиц

require_once 'dbpass.php'; // define('DB_PASS', 'password');

DB::connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PREF);
```

## 🏗️ DBObject - работа с таблицами как с объектами

### Определение таблицы
```php
class User extends \losthost\DB\DBObject {
    
    const METADATA = [
        'id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT "ID пользователя"',
        'username' => 'VARCHAR(50) NOT NULL COMMENT "Логин"',
        'email' => 'VARCHAR(100) COMMENT "Email"',
        'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1 COMMENT "Активен"',
        'created_at' => 'DATETIME NOT NULL COMMENT "Дата создания"',
        'PRIMARY KEY' => 'id',
        'UNIQUE INDEX username' => 'username',
        'INDEX idx_active' => 'is_active'
    ];
    
    public static function tableName() {
        return DB::$prefix . 'users';
    }
}
```

**Важно о METADATA:**
- Поля описываются как `'field' => 'SQL определение'`
- Индексы: `'PRIMARY KEY'`, `'UNIQUE INDEX name'`, `'INDEX name'`
- Для составных индексов используйте массивы: `['field1', 'field2']`
- Комментарии на русском поддерживаются
- **Даже если поле NOT NULL с DEFAULT - задавайте значение при создании объекта!**

### Автоматические миграции
```php
// Создает/обновляет таблицу по METADATA
User::initDataStructure();

// Принудительная перепроверка
User::initDataStructure(true);
```

**Как работает:** Библиотека хранит хэш метаданных в комментарии таблицы. При изменении METADATA автоматически выполняется ALTER TABLE.

### Базовые операции CRUD

#### Создание объекта
```php
// Новый объект
$user = new User();
$user->username = 'john_doe';
$user->email = 'john@example.com';
$user->is_active = true;
$user->created_at = new DateTimeImmutable();
$user->write(); // INSERT

// С данными сразу
$user = new User(['username' => 'jane_doe'], true);
// true = создать сразу (write)
```

#### Загрузка объекта
```php
// По ID
$user = new User(['id' => 123]);

// По любому полю
$user = new User(['username' => 'john_doe']);

// Проверка существования
try {
    $user = new User(['id' => 999]);
} catch (Exception $e) {
    // Not found
}
```

#### Обновление
```php
$user = new User(['id' => 123]);
$user->email = 'new@example.com';
$user->write(); // UPDATE (определяет автоматически)
```

#### Удаление
```php
$user->delete();
// После delete() объект становится "unuseable"
```

### Состояния объекта
```php
$user->isNew();      // true для новых объектов
$user->isModified(); // true если поля изменены
$user->asArray();    // все поля как массив
```

### Типы данных

#### DateTime
```php
// Запись
$user->created_at = new DateTimeImmutable();

// Чтение (возвращает DateTimeImmutable)
$date = $user->created_at;
echo $date->format('Y-m-d H:i:s');
```

#### Boolean (через TINYINT(1))
```php
// Запись
$user->is_active = true;  // сохраняется как 1
$user->is_active = false; // сохраняется как 0

// Чтение
if ($user->is_active) { ... }
```

#### NULL значения
```php
$user->email = null; // NULL в БД
$email = $user->email; // null при чтении
```

### Хуки жизненного цикла
```php
protected function intranInsert($comment, $data) {
    // Вызывается ВНУТРИ транзакции вставки
    // Можно менять данные перед сохранением
    parent::intranInsert($comment, $data);
}

protected function beforeInsert($comment, $data) { /* до вставки */ }
protected function afterInsert($comment, $data) { /* после вставки */ }
protected function beforeUpdate($comment, $data) { /* до обновления */ }
protected function afterUpdate($comment, $data) { /* после обновления */ }
protected function beforeDelete($comment, $data) { /* до удаления */ }
protected function afterDelete($comment, $data) { /* после удаления */ }
```

## 📚 DBList - работа с коллекциями

### Поиск объектов
```php
// Простой фильтр (массив условий)
$list = new DBList(User::class, ['is_active' => true]);

// Сложный фильтр (SQL с параметрами)
$list = new DBList(User::class, 
    'created_at > ? AND username LIKE ?', 
    ['2023-01-01', 'john%']
);

// С сортировкой
$list = new DBList(User::class, 
    'is_active = true ORDER BY created_at DESC'
);
```

### Итерация (lazy loading)
```php
$list = new DBList(User::class, ['is_active' => true]);

while ($user = $list->next()) {
    echo $user->username;
    // Объекты загружаются по одному при каждом next()
}
```

### Получение всех объектов
```php
$users = $list->asArray(); // Все объекты как массив
// Внимание: для больших выборок используйте next()!
```

## 🔍 DBView - произвольные запросы

### Итерация по результатам
```php
$sql = 'SELECT u.*, COUNT(o.id) as order_count 
        FROM [users] u 
        LEFT JOIN [orders] o ON o.user_id = u.id 
        WHERE u.is_active = ? 
        GROUP BY u.id 
        HAVING order_count > ?';

$view = new DBView($sql, [true, 5]);

while ($view->next()) {
    echo $view->username . ': ' . $view->order_count;
}

// Префиксы таблиц: [users] → prefix_users
```

### Результат как массив
```php
$array = $view->asArray(); // Все строки как массив ассоциативных массивов
```

## 🔢 DBValue - скалярные значения и одиночные строки

### Одиночное значение
```php
// Агрегатные функции
$count = new DBValue('SELECT COUNT(*) FROM [users] WHERE is_active = ?', [true]);
echo $count->{'COUNT(*)'}; // Доступ через имя поля

// С алиасом
$max = new DBValue('SELECT MAX(created_at) as last_date FROM [users]');
echo $max->last_date;

// Статический конструктор
$id = DBValue::new('SELECT MAX(id) as max_id FROM [users]')->max_id;
```

### Сложные условия
```php
$date = new DateTime('2023-01-01');
$user = new DBValue(
    'SELECT username FROM [users] WHERE created_at = ? AND is_active = ?',
    [$date, true]
);
echo $user->username;
```

## ⚙️ DB - низкоуровневые операции

### Транзакции
```php
DB::beginTransaction();
try {
    // Операции...
    DB::commit();
} catch (Exception $e) {
    DB::rollBack();
    throw $e;
}

// Проверка
if (DB::inTransaction()) { ... }
```

**Важно:** Библиотека НЕ переподключается при потере соединения во время транзакции (безопасность).

### Блокировки (GET_LOCK)
```php
if (DB::getLock('resource_name')) {
    try {
        // Критическая секция...
    } finally {
        DB::releaseLock('resource_name');
    }
}

// Проверка
if (DB::isFreeLock('resource_name')) { ... }
```

### Префиксы таблиц
```php
// В SQL запросах
$sql = 'SELECT * FROM [users]'; // → prefix_users

// Через DB::getTables()
$tables = DB::getTables('[users], [orders]'); // → ['prefix_users', 'prefix_orders']
```

### Автопереподключение
При потере соединения библиотека автоматически переподключается, кроме случаев:
- Во время транзакции
- При явном вызове `KILL CONNECTION_ID()`

## ⚠️ Важные особенности

### Обработка ошибок
**Всегда используйте try/catch!** Библиотека всегда бросает исключения:
- `-10002` - Not found
- `-10003` - Неизвестное поле/ошибка валидации  
- `-10013` - Недопустимое состояние (удаленный объект, транзакция)
- `-10014` - Рекурсия в событиях

### Производительность
- **Нет кэширования** запросов или объектов
- **`asArray()` загружает всё в память** - для больших выборок используйте `next()`
- **Нет пагинации** на уровне библиотеки
- **Lazy loading в DBList** - сначала ID, потом объекты по одному

### Ограничения
- ❌ **Нет отношений** между таблицами (JOIN, foreign keys)
- ❌ **Нет миграций данных**, только схемы (ALTER TABLE)
- ❌ **Индексы только через METADATA**, не динамически
- ❌ **Только MySQL** (использует специфичные функции)

## 🏆 Best practices

### 1. Всегда задавайте значения
```php
// Даже если поле NOT NULL с DEFAULT в БД:
$user = new User();
$user->is_active = true; // ← обязательно!
$user->created_at = new DateTimeImmutable(); // ← обязательно!
$user->write();
```

### 2. Используйте транзакции для групп операций
```php
DB::beginTransaction();
try {
    $user->write();
    $log->write();
    DB::commit();
} catch (Exception $e) {
    DB::rollBack();
    throw $e;
}
```

### 3. Для больших данных - итерация, не массив
```php
// ПЛОХО (вся выборка в памяти):
$all = $list->asArray();

// ХОРОШО (ленивая загрузка):
while ($item = $list->next()) {
    // обработка
}
```

### 4. Используйте хуки для бизнес-логики
```php
protected function intranInsert($comment, $data) {
    parent::intranInsert($comment, $data);
    // Логирование, валидация, вычисления...
}
```

### 5. Проверяйте существование объектов
```php
try {
    $user = new User(['id' => $id]);
} catch (Exception $e) {
    if ($e->getCode() == -10002) {
        // Объект не найден
    }
}
```

## 💼 Пример из практики - система балансов

```php
class DBBalanceTransaction extends \losthost\DB\DBObject {
    
    const METADATA = [
        'id' => 'BIGINT NOT NULL AUTO_INCREMENT',
        'user_id' => 'VARCHAR(50) NOT NULL',
        'amount' => 'DECIMAL(36,18) NOT NULL',
        'type' => 'ENUM("topup", "usage", "transfer")',
        'description' => 'TEXT',
        'created_at' => 'DATETIME NOT NULL',
        'PRIMARY KEY' => 'id',
        'INDEX idx_user' => 'user_id'
    ];
    
    // Бизнес-логика поверх ORM
    static public function transfer($from, $to, $amount, $desc = null) {
        if (!DB::inTransaction()) {
            DB::beginTransaction();
            $commit = true;
        }
        
        try {
            // Списание
            $out = new static();
            $out->user_id = $from;
            $out->amount = -$amount;
            $out->type = 'transfer';
            $out->description = $desc ?: "To $to";
            $out->created_at = new DateTimeImmutable();
            $out->write();
            
            // Зачисление  
            $in = new static();
            $in->user_id = $to;
            $in->amount = $amount;
            $in->type = 'transfer';
            $in->description = $desc ?: "From $from";
            $in->created_at = new DateTimeImmutable();
            $in->write();
            
            if ($commit) DB::commit();
            return ['out' => $out, 'in' => $in];
            
        } catch (Exception $e) {
            if ($commit) DB::rollBack();
            throw $e;
        }
    }
    
    static public function getBalance($user_id) {
        $sql = 'SELECT COALESCE(SUM(amount), 0) as balance 
                FROM '. static::tableName(). ' 
                WHERE user_id = ?';
        return (float)DBValue::new($sql, [$user_id])->balance;
    }
}
```

## 🎯 Когда использовать

### Хорошо подходит для:
- ✅ Боты (Telegram и другие)
- ✅ Веб-приложения средней сложности
- ✅ Фоновые задачи и очереди
- ✅ Проекты с частыми изменениями схемы
- ✅ Системы с конкурентным доступом (блокировки)

### Не подходит для:
- ❌ Сложные OLAP-запросы с множеством JOIN
- ❌ Высоконагруженные системы (нет кэширования)
- ❌ Проекты с частыми миграциями данных
- ❌ Когда нужна поддержка нескольких СУБД

## 🔧 Отладка

### Логирование
```php
// В хуках
protected function intranInsert($comment, $data) {
    parent::intranInsert($comment, $data);
    error_log("Insert: " . $this->id);
}
```

### Проверка запросов
```php
// Все запросы через prepared statements
$sth = DB::prepare('SELECT * FROM [users] WHERE id = ?');
$sth->execute([$id]);
```

## 📚 Резюме

**losthost/db** - это "золотая середина" между сырым PDO и тяжелыми ORM:
- 🚀 Быстрый старт (метаданные → готовая таблица)
- 🛡️ Безопасность (prepared statements, транзакции)
- 📦 Минимум кода (CRUD в 3 строки)
- 🔄 Автомиграции (хэш в комментарии таблицы)
- 🧩 Модульность (используйте только нужные слои)

**Идеально для:** быстрой разработки бизнес-логики без погружения в детали БД, но с полным контролем когда нужно.
