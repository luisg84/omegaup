<?php
/** ******************************************************************************* *
  *                    !ATENCION!                                                   *
  *                                                                                 *
  * Este codigo es generado automaticamente. Si lo modificas tus cambios seran      *
  * reemplazados la proxima vez que se autogenere el codigo.                        *
  *                                                                                 *
  * ******************************************************************************* */

namespace OmegaUp\DAO\Base;

/** ProblemsetAccessLog Data Access Object (DAO) Base.
 *
 * Esta clase contiene toda la manipulacion de bases de datos que se necesita
 * para almacenar de forma permanente y recuperar instancias de objetos
 * {@link \OmegaUp\DAO\VO\ProblemsetAccessLog}.
 * @access public
 * @abstract
 */
abstract class ProblemsetAccessLog {
    /**
     * Obtener todas las filas.
     *
     * Esta funcion leerá todos los contenidos de la tabla en la base de datos
     * y construirá un arreglo que contiene objetos de tipo
     * {@link \OmegaUp\DAO\VO\ProblemsetAccessLog}.
     * Este método consume una cantidad de memoria proporcional al número de
     * registros regresados, así que sólo debe usarse cuando la tabla en
     * cuestión es pequeña o se proporcionan parámetros para obtener un menor
     * número de filas.
     *
     * @param ?int $pagina Página a ver.
     * @param int $filasPorPagina Filas por página.
     * @param ?string $orden Debe ser una cadena con el nombre de una columna en la base de datos.
     * @param string $tipoDeOrden 'ASC' o 'DESC' el default es 'ASC'
     *
     * @return \OmegaUp\DAO\VO\ProblemsetAccessLog[] Un arreglo que contiene objetos del tipo
     * {@link \OmegaUp\DAO\VO\ProblemsetAccessLog}.
     *
     * @psalm-return array<int, \OmegaUp\DAO\VO\ProblemsetAccessLog>
     */
    final public static function getAll(
        ?int $pagina = null,
        int $filasPorPagina = 100,
        ?string $orden = null,
        string $tipoDeOrden = 'ASC'
    ) : array {
        $sql = 'SELECT `Problemset_Access_Log`.`problemset_id`, `Problemset_Access_Log`.`identity_id`, `Problemset_Access_Log`.`ip`, `Problemset_Access_Log`.`time` from Problemset_Access_Log';
        if (!is_null($orden)) {
            $sql .= ' ORDER BY `' . \OmegaUp\MySQLConnection::getInstance()->escape($orden) . '` ' . ($tipoDeOrden == 'DESC' ? 'DESC' : 'ASC');
        }
        if (!is_null($pagina)) {
            $sql .= ' LIMIT ' . (($pagina - 1) * $filasPorPagina) . ', ' . (int)$filasPorPagina;
        }
        $allData = [];
        foreach (\OmegaUp\MySQLConnection::getInstance()->GetAll($sql) as $row) {
            $allData[] = new \OmegaUp\DAO\VO\ProblemsetAccessLog($row);
        }
        return $allData;
    }

    /**
     * Crear registros.
     *
     * Este metodo creará una nueva fila en la base de datos de acuerdo con los
     * contenidos del objeto {@link \OmegaUp\DAO\VO\ProblemsetAccessLog}
     * suministrado.
     *
     * @param \OmegaUp\DAO\VO\ProblemsetAccessLog $Problemset_Access_Log El
     * objeto de tipo {@link \OmegaUp\DAO\VO\ProblemsetAccessLog} a crear.
     *
     * @return int Un entero mayor o igual a cero identificando el número de filas afectadas.
     */
    final public static function create(\OmegaUp\DAO\VO\ProblemsetAccessLog $Problemset_Access_Log) : int {
        $sql = 'INSERT INTO Problemset_Access_Log (`problemset_id`, `identity_id`, `ip`, `time`) VALUES (?, ?, ?, ?);';
        $params = [
            is_null($Problemset_Access_Log->problemset_id) ? null : (int)$Problemset_Access_Log->problemset_id,
            is_null($Problemset_Access_Log->identity_id) ? null : (int)$Problemset_Access_Log->identity_id,
            is_null($Problemset_Access_Log->ip) ? null : (int)$Problemset_Access_Log->ip,
            \OmegaUp\DAO\DAO::toMySQLTimestamp($Problemset_Access_Log->time),
        ];
        \OmegaUp\MySQLConnection::getInstance()->Execute($sql, $params);
        $affectedRows = \OmegaUp\MySQLConnection::getInstance()->Affected_Rows();
        if ($affectedRows == 0) {
            return 0;
        }

        return $affectedRows;
    }
}
