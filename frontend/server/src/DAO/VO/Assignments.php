<?php
/** ******************************************************************************* *
  *                    !ATENCION!                                                   *
  *                                                                                 *
  * Este codigo es generado automaticamente. Si lo modificas tus cambios seran      *
  * reemplazados la proxima vez que se autogenere el codigo.                        *
  *                                                                                 *
  * ******************************************************************************* */

namespace OmegaUp\DAO\VO;

/**
 * Value Object class for table `Assignments`.
 *
 * @access public
 */
class Assignments extends \OmegaUp\DAO\VO\VO {
    const FIELD_NAMES = [
        'assignment_id' => true,
        'course_id' => true,
        'problemset_id' => true,
        'acl_id' => true,
        'name' => true,
        'description' => true,
        'alias' => true,
        'publish_time_delay' => true,
        'assignment_type' => true,
        'start_time' => true,
        'finish_time' => true,
        'max_points' => true,
        'order' => true,
    ];

    function __construct(?array $data = null) {
        if (empty($data)) {
            return;
        }
        $unknownColumns = array_diff_key($data, self::FIELD_NAMES);
        if (!empty($unknownColumns)) {
            throw new \Exception('Unknown columns: ' . join(', ', array_keys($unknownColumns)));
        }
        if (isset($data['assignment_id'])) {
            $this->assignment_id = (int)$data['assignment_id'];
        }
        if (isset($data['course_id'])) {
            $this->course_id = (int)$data['course_id'];
        }
        if (isset($data['problemset_id'])) {
            $this->problemset_id = (int)$data['problemset_id'];
        }
        if (isset($data['acl_id'])) {
            $this->acl_id = (int)$data['acl_id'];
        }
        if (isset($data['name'])) {
            $this->name = strval($data['name']);
        }
        if (isset($data['description'])) {
            $this->description = strval($data['description']);
        }
        if (isset($data['alias'])) {
            $this->alias = strval($data['alias']);
        }
        if (isset($data['publish_time_delay'])) {
            $this->publish_time_delay = (int)$data['publish_time_delay'];
        }
        if (isset($data['assignment_type'])) {
            $this->assignment_type = strval($data['assignment_type']);
        }
        if (isset($data['start_time'])) {
            /**
             * @var string|int|float $data['start_time']
             * @var int $this->start_time
             */
            $this->start_time = \OmegaUp\DAO\DAO::fromMySQLTimestamp($data['start_time']);
        }
        if (isset($data['finish_time'])) {
            /**
             * @var string|int|float $data['finish_time']
             * @var int $this->finish_time
             */
            $this->finish_time = \OmegaUp\DAO\DAO::fromMySQLTimestamp($data['finish_time']);
        }
        if (isset($data['max_points'])) {
            $this->max_points = (float)$data['max_points'];
        }
        if (isset($data['order'])) {
            $this->order = (int)$data['order'];
        }
    }

    /**
     * [Campo no documentado]
     * Llave Primaria
     * Auto Incremento
     *
     * @var int|null
     */
    public $assignment_id = 0;

    /**
     * [Campo no documentado]
     *
     * @var int|null
     */
    public $course_id = null;

    /**
     * [Campo no documentado]
     *
     * @var int|null
     */
    public $problemset_id = null;

    /**
     * La lista de control de acceso compartida con el curso
     *
     * @var int|null
     */
    public $acl_id = null;

    /**
     * [Campo no documentado]
     *
     * @var string|null
     */
    public $name = null;

    /**
     * [Campo no documentado]
     *
     * @var string|null
     */
    public $description = null;

    /**
     * [Campo no documentado]
     *
     * @var string|null
     */
    public $alias = null;

    /**
     * [Campo no documentado]
     *
     * @var int|null
     */
    public $publish_time_delay = null;

    /**
     * [Campo no documentado]
     *
     * @var string|null
     */
    public $assignment_type = null;

    /**
     * [Campo no documentado]
     *
     * @var int
     */
    public $start_time = 946706400; // 2000-01-01 06:00:00

    /**
     * [Campo no documentado]
     *
     * @var int
     */
    public $finish_time = 946706400; // 2000-01-01 06:00:00

    /**
     * La cantidad total de puntos que se pueden obtener.
     *
     * @var float
     */
    public $max_points = 0.00;

    /**
     * Define el orden de aparición de los problemas/tareas
     *
     * @var int
     */
    public $order = 1;
}
