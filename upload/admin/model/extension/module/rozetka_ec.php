<?php
class ModelExtensionModuleRozetkaEc extends Model {	

    /**
     * Видалення модуля: видаляє таблицю `oc_rozetka_ec_uuid` з бази даних.
     *
     * @return void
     */
    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "rozetka_ec_uuid`");
    }

    /**
     * Отримує ID замовлення за його UUID.
     *
     * @param string $uuid Унікальний ідентифікатор Rozetka.
     * 
     * @return int|false ID замовлення, якщо знайдено, або `false`, якщо немає відповідного запису.
     */
    public function getOrderId($uuid) {		
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "rozetka_ec_uuid` WHERE `uuid` = '" . $this->db->escape($uuid) . "'");

        return ($query->num_rows) ? (int)$query->row['order_id'] : false;
    }
}
