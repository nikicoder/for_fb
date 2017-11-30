<?php
/*
Задача: создание первоначальной структуры по шаблону.
Шаблон это иеррархическое дерево следующего вида
Пункт 1
 - подпункт 1.1
 - подпункт 1.2
  - подпункт 1.2.1
 - подпункт 1.3
Пункт 2
... и так далее

Из файла создается первоначальная структура объекта и записывается в БД.

Файл имеет вот такой вид:
1|Пункт 1
2|подпункт 1.1
2|подпункт 1.2

где перед | стоит уровень в иеррархии (далее k).
Допущения следующие: 
 - все объекты с уровнем 1 являются корневымы;
 - все объекты с уровнем 1+ являются потомками ближнего к ним k-1;
 - шаблон не содержит ошибок, т.е. функция всецело полагается на шаблон 
 и никаких проверок целостности структуры не делается
*/

// Код написан для фреймворка CodeIgniter, для использования в других системах/фреймворках
// необходимо переписать запросы к БД и путь к файлу (APPATH)
public function createStructire($id, $template)
{
    // путь к шаблону
    $template_file = APPPATH . 'misc' . DIRECTORY_SEPARATOR . $template . '_template';
    if(!is_file($template_file)) {
        return FALSE;
    } 

    $template_data = explode("\n", file_get_contents($template_file));

    // элементы структуры объекта (object_id) хранятся в отдельной (этой) таблице
    // сюда происходит запись начальной структуры из шаблона
    $base_query = 'INSERT INTO "__table__" ("name", "metadata", "object_id", "state") 
    VALUES (?, ?, ?, ?) 
    RETURNING *';
    
    $current_path = [];

    foreach($template_data as $srt_item) {

        $item_data = explode('|', $srt_item);

        $insert_data = [
            $this->db->escape_str($item_data[1]),
            json_encode([]),
            $id,
            $this->db->escape_str('default')
        ];

        $query = $this->db->query($base_query, $insert_data, TRUE);
        // фича PostgreSQL RETURNING * (см шаблон запроса) -- возвращает добавленную строку
        // из которой извлекаем id, он понадобится далее
        $data = $query->row_array();

        if(isset($data['id'])) {

            // устанавливаем полученный после инсерта id в качестве пути
            $current_path[$item_data[0]] = $data['id'];
            // убираем все элементы уровнем (k) выше чем текущий элемент
            $current_path_idx = array_keys($current_path);
            foreach($current_path_idx as $lvl) {
                if($lvl > $item_data[0]) {
                    unset($current_path[$lvl]);
                }
            }

            // в таблице для хранения структуры используется расширение ltree 
            // по этому для каждого элемента хранится его полный путь от родиесли предыдущий был 3-го уровня, убираем бОльшие значения если следующий 1-2 уровеньтеля
            // вида 1.3.5.9, где текущий элемент с id = 9 и все его родители до корня
            $hierarchy = implode('.', $current_path);
            $this->db->update('__table__', ['hierarchy' => $hierarchy], ['id' => $data['id']]);
        }
    }

    return TRUE;
}

// файл подготовлен для https://www.facebook.com/nikicoder/posts/117436665706178
// 30.11.2017