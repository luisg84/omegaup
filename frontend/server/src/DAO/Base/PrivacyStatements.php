<?php
/** ******************************************************************************* *
  *                    !ATENCION!                                                   *
  *                                                                                 *
  * Este codigo es generado automaticamente. Si lo modificas tus cambios seran      *
  * reemplazados la proxima vez que se autogenere el codigo.                        *
  *                                                                                 *
  * ******************************************************************************* */

namespace OmegaUp\DAO\Base;

/** PrivacyStatements Data Access Object (DAO) Base.
 *
 * Esta clase contiene toda la manipulacion de bases de datos que se necesita
 * para almacenar de forma permanente y recuperar instancias de objetos
 * {@link \OmegaUp\DAO\VO\PrivacyStatements}.
 * @access public
 * @abstract
 */
abstract class PrivacyStatements {
    /**
     * Actualizar registros.
     *
     * @param \OmegaUp\DAO\VO\PrivacyStatements $PrivacyStatements El objeto de tipo PrivacyStatements a actualizar.
     *
     * @return int Número de filas afectadas
     */
    final public static function update(\OmegaUp\DAO\VO\PrivacyStatements $PrivacyStatements) : int {
        $sql = 'UPDATE `PrivacyStatements` SET `git_object_id` = ?, `type` = ? WHERE `privacystatement_id` = ?;';
        $params = [
            $PrivacyStatements->git_object_id,
            $PrivacyStatements->type,
            (int)$PrivacyStatements->privacystatement_id,
        ];
        \OmegaUp\MySQLConnection::getInstance()->Execute($sql, $params);
        return \OmegaUp\MySQLConnection::getInstance()->Affected_Rows();
    }

    /**
     * Obtener {@link \OmegaUp\DAO\VO\PrivacyStatements} por llave primaria.
     *
     * Este metodo cargará un objeto {@link \OmegaUp\DAO\VO\PrivacyStatements}
     * de la base de datos usando sus llaves primarias.
     *
     * @return ?\OmegaUp\DAO\VO\PrivacyStatements Un objeto del tipo
     * {@link \OmegaUp\DAO\VO\PrivacyStatements} o NULL si no hay tal
     * registro.
     */
    final public static function getByPK(int $privacystatement_id) : ?\OmegaUp\DAO\VO\PrivacyStatements {
        $sql = 'SELECT `PrivacyStatements`.`privacystatement_id`, `PrivacyStatements`.`git_object_id`, `PrivacyStatements`.`type` FROM PrivacyStatements WHERE (privacystatement_id = ?) LIMIT 1;';
        $params = [$privacystatement_id];
        $row = \OmegaUp\MySQLConnection::getInstance()->GetRow($sql, $params);
        if (empty($row)) {
            return null;
        }
        return new \OmegaUp\DAO\VO\PrivacyStatements($row);
    }

    /**
     * Eliminar registros.
     *
     * Este metodo eliminará el registro identificado por la llave primaria en
     * el objeto {@link \OmegaUp\DAO\VO\PrivacyStatements} suministrado.
     * Una vez que se ha eliminado un objeto, este no puede ser restaurado
     * llamando a {@link replace()}, ya que este último creará un nuevo
     * registro con una llave primaria distinta a la que estaba en el objeto
     * eliminado.
     *
     * Si no puede encontrar el registro a eliminar,
     * {@link \OmegaUp\Exceptions\NotFoundException} será arrojada.
     *
     * @param \OmegaUp\DAO\VO\PrivacyStatements $PrivacyStatements El
     * objeto de tipo \OmegaUp\DAO\VO\PrivacyStatements a eliminar
     *
     * @throws \OmegaUp\Exceptions\NotFoundException Se arroja cuando no se
     * encuentra el objeto a eliminar en la base de datos.
     */
    final public static function delete(\OmegaUp\DAO\VO\PrivacyStatements $PrivacyStatements) : void {
        $sql = 'DELETE FROM `PrivacyStatements` WHERE privacystatement_id = ?;';
        $params = [$PrivacyStatements->privacystatement_id];

        \OmegaUp\MySQLConnection::getInstance()->Execute($sql, $params);
        if (\OmegaUp\MySQLConnection::getInstance()->Affected_Rows() == 0) {
            throw new \OmegaUp\Exceptions\NotFoundException('recordNotFound');
        }
    }

    /**
     * Obtener todas las filas.
     *
     * Esta funcion leerá todos los contenidos de la tabla en la base de datos
     * y construirá un arreglo que contiene objetos de tipo
     * {@link \OmegaUp\DAO\VO\PrivacyStatements}.
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
     * @return \OmegaUp\DAO\VO\PrivacyStatements[] Un arreglo que contiene objetos del tipo
     * {@link \OmegaUp\DAO\VO\PrivacyStatements}.
     *
     * @psalm-return array<int, \OmegaUp\DAO\VO\PrivacyStatements>
     */
    final public static function getAll(
        ?int $pagina = null,
        int $filasPorPagina = 100,
        ?string $orden = null,
        string $tipoDeOrden = 'ASC'
    ) : array {
        $sql = 'SELECT `PrivacyStatements`.`privacystatement_id`, `PrivacyStatements`.`git_object_id`, `PrivacyStatements`.`type` from PrivacyStatements';
        if (!is_null($orden)) {
            $sql .= ' ORDER BY `' . \OmegaUp\MySQLConnection::getInstance()->escape($orden) . '` ' . ($tipoDeOrden == 'DESC' ? 'DESC' : 'ASC');
        }
        if (!is_null($pagina)) {
            $sql .= ' LIMIT ' . (($pagina - 1) * $filasPorPagina) . ', ' . (int)$filasPorPagina;
        }
        $allData = [];
        foreach (\OmegaUp\MySQLConnection::getInstance()->GetAll($sql) as $row) {
            $allData[] = new \OmegaUp\DAO\VO\PrivacyStatements($row);
        }
        return $allData;
    }

    /**
     * Crear registros.
     *
     * Este metodo creará una nueva fila en la base de datos de acuerdo con los
     * contenidos del objeto {@link \OmegaUp\DAO\VO\PrivacyStatements}
     * suministrado.
     *
     * @param \OmegaUp\DAO\VO\PrivacyStatements $PrivacyStatements El
     * objeto de tipo {@link \OmegaUp\DAO\VO\PrivacyStatements} a crear.
     *
     * @return int Un entero mayor o igual a cero identificando el número de filas afectadas.
     */
    final public static function create(\OmegaUp\DAO\VO\PrivacyStatements $PrivacyStatements) : int {
        $sql = 'INSERT INTO PrivacyStatements (`git_object_id`, `type`) VALUES (?, ?);';
        $params = [
            $PrivacyStatements->git_object_id,
            $PrivacyStatements->type,
        ];
        \OmegaUp\MySQLConnection::getInstance()->Execute($sql, $params);
        $affectedRows = \OmegaUp\MySQLConnection::getInstance()->Affected_Rows();
        if ($affectedRows == 0) {
            return 0;
        }
        $PrivacyStatements->privacystatement_id = \OmegaUp\MySQLConnection::getInstance()->Insert_ID();

        return $affectedRows;
    }
}
