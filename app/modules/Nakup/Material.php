<?php

use Nette\Object;

/**
 * Model Material class
 */
class Material extends Model
{
	/** @var string
	 *  @table
	 */
	private $table = 'material';


    public function __construct($arr = array())
    {
        parent::__construct($arr);
    }

	/**
	 * Vrací obsah tabulky
	 * @param int idp = id_product
	 * @param int what = index of filter (used: 0..none, 1..cena_cm=0, 2..cena_cm>0,...)
	 * @return record set
	 */
	public function show($idp=0,$what=0)
	{
		$sql_cmd = "";
		$cond = "";
		if($idp>0){
			switch (abs($what)){
				case 0:  //all
				{
					$sql_cmd = "SELECT m.*, v.*, me.zkratka [mena] FROM material m 
												LEFT JOIN vazby v ON m.id=v.id_material 
												LEFT JOIN meny me ON m.id_meny=me.id";
					$cond = "v.id_vyssi=$idp";
					break;
				}
				case 9:  //all
				{
					$sql_cmd = "SELECT m.*, v.*, me.zkratka [mena] FROM material m 
												LEFT JOIN vazby v ON m.id=v.id_material 
												LEFT JOIN meny me ON m.id_meny=me.id";
					$cond = "v.id_vyssi=$idp";
					break;
				}
				case 1: //cena_cm<=0
				{
					$sql_cmd = "SELECT m.*, v.*, me.zkratka [mena] FROM material m	
												LEFT JOIN vazby v ON m.id=v.id_material 
												LEFT JOIN meny me ON m.id_meny=me.id
											";
					$cond = "v.id_vyssi = $idp AND (m.cena_cm <= 0 OR m.cena_cm is null)";
					break;

				}
				case 2: //cena_cm>0
				{
					$sql_cmd = "SELECT m.*, v.*, me.zkratka [mena] FROM material m	
												LEFT JOIN vazby v ON m.id=v.id_material 
												LEFT JOIN meny me ON m.id_meny=me.id
											";
					$cond = "v.id_vyssi = $idp AND m.cena_cm > 0";
					break;
				}
			}
		} else {
			switch (abs($what)){
				case 0:  //all
				{
					$sql_cmd = "SELECT m.*, me.zkratka [mena] FROM material m 
												LEFT JOIN meny me ON m.id_meny=me.id
											";
					$cond = "m.id > 0";
					break;
				}
				case 1: //cena_cm<=0
				{
					$sql_cmd = "SELECT m.*, me.zkratka [mena] FROM material m	
												LEFT JOIN meny me ON m.id_meny=me.id
											";
					$cond = "(m.cena_cm <= 0 OR m.cena_cm is null)";
					break;

				}
				case 2: //cena_cm>0
				{
					$sql_cmd = "SELECT m.*, me.zkratka [mena] FROM material m	
												LEFT JOIN meny me ON m.id_meny=me.id
											";
					$cond = "m.cena_cm > 0";
					break;
				}
			}
		}
		$ordsql = '';
		$ordfil = 'cena_kc DESC';
		$ordovr = 'm.id';
		if($what < 0){
			$ordsql = ' ORDER BY '.$ordfil;
			$ordovr = $ordfil.", ".$ordovr;
		}
		if ($cond<>''){
			$cond = ' WHERE '.$cond;
			if ($this->filter<>''){$cond .= " AND m.zkratka+m.nazev LIKE '%$this->filter%'";}
		} else {
			if ($this->filter<>''){$cond = " WHERE m.zkratka+m.nazev LIKE '%$this->filter%'";}
		}
		if($this->limit==0 && $this->offset==0){
			$rslt = $this->connection->query("$sql_cmd $cond $ordsql");
		} else {
			//implementace stránkování
			$sql_pgs = $this->pagedSql($sql_cmd.$cond, '', $ordovr);
			$rslt = $this->connection->query($sql_pgs);
		}
		return $rslt->fetchAll();
	}

	/**
	 * Vrací data pro konkrétní záznam
	 * @param int
	 * @return record set
	 */
	public function find($id, $id_produkt = 0)
	{
		$cond = "";
		if($id_produkt>0){$cond = " AND v.id_vyssi = $id_produkt";}
		
		return dibi::dataSource("SELECT m.id [id],m.id_k2 [id_k2],m.zkratka [zkratka], m.nazev [nazev], ROUND(m.cena_cm,5) [cena_cm], ROUND(m.cena_kc,5) [cena_kc], ROUND(m.cena_kc2,5) [cena_kc2], ROUND(m.cena_kc3,5) [cena_kc3], m.id_kurzy [id_kurzy], m.id_meny [id_meny], m.id_merne_jednotky [id_merne_jednotky], 
										mj.zkratka [jednotka], mj.koeficient [koeficient],
										ROUND(k.kurz_nakupni,5) [kurz_nakupni],
										ROUND(k.kurz_prodejni,5) [kurz_prodejni],
										k.platnost_od, k.platnost_do,
										COALESCE(mn.zkratka, 'CZK') [mena],
										v.mnozstvi [p_pocet]
								FROM material m
									LEFT JOIN vazby v ON m.id=v.id_material 
									LEFT JOIN merne_jednotky mj ON m.id_merne_jednotky=mj.id
									LEFT JOIN kurzy k ON m.id_kurzy=k.id
									LEFT JOIN meny mn ON m.id_meny=mn.id
								WHERE m.id = $id $cond");
	}
	
	/**
	 *
	 * @return type array
	 */
	public function countNoPrices(){
		return $this->connection->select("COUNT(*) [pocet], (SELECT COUNT(*) FROM $this->table) [celkem]")
						->from($this->table)->where("cena_cm is NULL OR cena_cm=%i", 0)->fetch();
	}
	
	/**
	 * Update data in the table
	 * @params int, array
	 * @return mixed
	 */
	public function update($id, $data = array(), $id_produkty = 0, $pocet = 0)
	{
		if($id_produkty>0 && $pocet > 0){
			// update vazeb
			$datav = array('mnozstvi' => $pocet);
			$this->connection->update('vazby', $datav)->where("id_vyssi=$id_produkty AND id_material=%i", $id)->execute();
		}
		return $this->connection->update($this->table, $data)->where('id=%i', $id)->execute();
	}

	/**
	 * Update data in the table
	 * @params int, array
	 * @return mixed
	 */
	public function updateK2id($id, $data)
	{
//		dumpBar($data, 'K2 id');
		return $this->connection->update($this->table, $data)->where('id=%i', $id)->execute();
	}

	/**
	 * Update data in the table
	 * @params int, array
	 * @return mixed
	 */
	public function updateK2price($id, $data)
	{
//		dumpBar($data['id_k2'], 'K2 id');
		return $this->connection->update($this->table, $data)->where('id=%i', $id)->execute();
	}

	/**
	 * Inserts data to the table
	 * @param array
	 * @return Identifier
	 */
	public function insert($data = array(), $id_produkty = 0, $pocet = 0)
	{
		$idm = $this->connection->insert($this->table, $data)->execute(dibi::IDENTIFIER);
		if($id_produkty>0 && $pocet > 0){
			$datav = array('id_vyssi' => $id_produkty, 'id_material' => $idm, 'mnozstvi' => $pocet);
			$this->connection->insert('vazby', $datav)->execute();
		}
//		dumpBar($data['id_k2'], 'K2 id');
		return $idm;
	}
	
	/**
	 * Recaclutate prices of material BOM of product
	 * @param type $idproduktu
	 * @param type $koeficient
	 * @return type 
	 */
	public function insertMatPrices($idproduktu, $koeficient, $meze=FALSE)
	{
		if($meze & $meze<>''){
			// alternativní cena
			$sql_cmd = "UPDATE material  
						SET cena_kc3 = cena_kc * $koeficient
						FROM material m
									LEFT JOIN vazby v ON m.id=v.id_material 
									LEFT JOIN meny me ON m.id_meny = me.id
									WHERE v.id_vyssi=$idproduktu";
			dibi::query($sql_cmd);
			
			// cena s mezemi - kalkulační
			$sql_cmd = "UPDATE material SET cena_kc2 = CASE";
			$case = '';
			for($i = 0; $i < count($meze); ++$i) {
				$do = $meze[$i]['mez'];
				$koef = 1 + $meze[$i]['sazba']/100;
				$max = 0;
				if($do>0){
					// menší než
					if($max<$do){$max=$do;}
					$case .= " WHEN cena_kc < $do THEN cena_kc * $koef";
				} else {
					// větší nebo rovno max
					$case .= " WHEN cena_kc >= $max THEN cena_kc * $koef";
				}
			}
			$sql_cmd .= $case .	" END
						FROM material m
									LEFT JOIN vazby v ON m.id=v.id_material 
									LEFT JOIN meny me ON m.id_meny = me.id
									WHERE v.id_vyssi=$idproduktu";
		} else {
			// alternativní cena - vynulování
			$sql_cmd = "UPDATE material  
						SET cena_kc3 = 0
						FROM material m
									LEFT JOIN vazby v ON m.id=v.id_material 
									LEFT JOIN meny me ON m.id_meny = me.id
									WHERE v.id_vyssi=$idproduktu";
			dibi::query($sql_cmd);
			// cena kalkulační
			$sql_cmd = "UPDATE material  
						SET cena_kc2 = cena_kc * $koeficient
						FROM material m
									LEFT JOIN vazby v ON m.id=v.id_material 
									LEFT JOIN meny me ON m.id_meny = me.id
									WHERE v.id_vyssi=$idproduktu";
		}
		//dd($sql_cmd,'SQL');
		//return false;
		return dibi::query($sql_cmd);
	}
	
	
	public function sumBOM($idprodukt)
	{
		$data = dibi::query("SELECT sum(m.cena_kc) [skc], sum(m.cena_kc2) [skc2], sum(m.cena_kc3) [skc3]
						FROM material m
							LEFT JOIN vazby v ON m.id=v.id_material 
							LEFT JOIN meny me ON m.id_meny=me.id
							WHERE v.id_vyssi=$idprodukt")->fetchAll();
		foreach ($data as $d){
			$ret['sumNaklad'] = $d['skc'];
			$ret['sumProdej'] = $d['skc2'];
			$ret['sumProAlt'] = $d['skc3'];
		}
		return $ret;
	}

	/**
	 * Deletes record in the table
	 * @param int
	 * @return mixed
	 */
//	public function delete($id)
//	{
//		return $this->connection->delete($this->table)->where('id=%i', $id)->execute();
//	}

	/**
	 * Deletes 1 record [or each assignet to product in table vazby] in the table
	 * @param int
	 * @return mixed
	 */
	public function delete($id, $id_produkt = 0)
	{
		if($id>0){
			return $this->connection->delete($this->table)->where('id=%i', $id)->execute();
		}
		if($id==0 && $id_produkt>0){
			return dibi::query("DELETE FROM material 
									WHERE id IN 
									(SELECT id_material FROM vazby WHERE id_vyssi=$id_produkt 
											AND id_material is not null)
								");
		}
	}

	/**
	 * Count noprices i material by id_produkty
	 * @param type $idprodukt
	 * @return type
	 */
	public function countNoSalePrices($id_produkty)
	{
		return dibi::query("SELECT COUNT(*) [cnt] FROM material m
							LEFT JOIN vazby v ON m.id=v.id_material 
							WHERE CAST(m.cena_kc2 AS money) <= 0 AND v.id_vyssi=$id_produkty")->fetchSingle();
	}
	
}


