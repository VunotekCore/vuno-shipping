-- Migration 012: Seed de los 153 municipios oficiales de Nicaragua en subzone.
-- Agrupados por departamento (zone.name). Compatible con MySQL 8.0. Idempotente
-- gracias a la UNIQUE KEY unique_zone_subzone (zone_id, name) + INSERT IGNORE.
-- Los INSERT usan subselect sobre zone.name porque los IDs de zone pueden variar
-- entre instalaciones.

INSERT IGNORE INTO subzone (zone_id, name)
SELECT id, 'Boaco' FROM zone WHERE name = 'Boaco' UNION ALL
SELECT id, 'Camoapa' FROM zone WHERE name = 'Boaco' UNION ALL
SELECT id, 'San José de los Remates' FROM zone WHERE name = 'Boaco' UNION ALL
SELECT id, 'San Lorenzo' FROM zone WHERE name = 'Boaco' UNION ALL
SELECT id, 'Santa Lucía' FROM zone WHERE name = 'Boaco' UNION ALL
SELECT id, 'Teustepe' FROM zone WHERE name = 'Boaco' UNION ALL

SELECT id, 'Diriamba' FROM zone WHERE name = 'Carazo' UNION ALL
SELECT id, 'Dolores' FROM zone WHERE name = 'Carazo' UNION ALL
SELECT id, 'El Rosario' FROM zone WHERE name = 'Carazo' UNION ALL
SELECT id, 'Jinotepe' FROM zone WHERE name = 'Carazo' UNION ALL
SELECT id, 'La Conquista' FROM zone WHERE name = 'Carazo' UNION ALL
SELECT id, 'La Paz de Carazo' FROM zone WHERE name = 'Carazo' UNION ALL
SELECT id, 'San Marcos' FROM zone WHERE name = 'Carazo' UNION ALL
SELECT id, 'Santa Teresa' FROM zone WHERE name = 'Carazo' UNION ALL

SELECT id, 'Chichigalpa' FROM zone WHERE name = 'Chinandega' UNION ALL
SELECT id, 'Chinandega' FROM zone WHERE name = 'Chinandega' UNION ALL
SELECT id, 'Cinco Pinos' FROM zone WHERE name = 'Chinandega' UNION ALL
SELECT id, 'Corinto' FROM zone WHERE name = 'Chinandega' UNION ALL
SELECT id, 'El Realejo' FROM zone WHERE name = 'Chinandega' UNION ALL
SELECT id, 'El Viejo' FROM zone WHERE name = 'Chinandega' UNION ALL
SELECT id, 'Posoltega' FROM zone WHERE name = 'Chinandega' UNION ALL
SELECT id, 'Puerto Morazán' FROM zone WHERE name = 'Chinandega' UNION ALL
SELECT id, 'San Francisco del Norte' FROM zone WHERE name = 'Chinandega' UNION ALL
SELECT id, 'San Pedro del Norte' FROM zone WHERE name = 'Chinandega' UNION ALL
SELECT id, 'Somotillo' FROM zone WHERE name = 'Chinandega' UNION ALL
SELECT id, 'Santo Tomás del Norte' FROM zone WHERE name = 'Chinandega' UNION ALL
SELECT id, 'Villanueva' FROM zone WHERE name = 'Chinandega' UNION ALL

SELECT id, 'Acoyapa' FROM zone WHERE name = 'Chontales' UNION ALL
SELECT id, 'Comalapa' FROM zone WHERE name = 'Chontales' UNION ALL
SELECT id, 'El Coral' FROM zone WHERE name = 'Chontales' UNION ALL
SELECT id, 'Juigalpa' FROM zone WHERE name = 'Chontales' UNION ALL
SELECT id, 'La Libertad' FROM zone WHERE name = 'Chontales' UNION ALL
SELECT id, 'San Francisco de Cuapa' FROM zone WHERE name = 'Chontales' UNION ALL
SELECT id, 'San Pedro de Lóvago' FROM zone WHERE name = 'Chontales' UNION ALL
SELECT id, 'Santo Domingo' FROM zone WHERE name = 'Chontales' UNION ALL
SELECT id, 'Santo Tomás' FROM zone WHERE name = 'Chontales' UNION ALL
SELECT id, 'Villa Sandino' FROM zone WHERE name = 'Chontales' UNION ALL

SELECT id, 'Condega' FROM zone WHERE name = 'Estelí' UNION ALL
SELECT id, 'Estelí' FROM zone WHERE name = 'Estelí' UNION ALL
SELECT id, 'La Trinidad' FROM zone WHERE name = 'Estelí' UNION ALL
SELECT id, 'Pueblo Nuevo' FROM zone WHERE name = 'Estelí' UNION ALL
SELECT id, 'San Juan de Limay' FROM zone WHERE name = 'Estelí' UNION ALL
SELECT id, 'San Nicolás' FROM zone WHERE name = 'Estelí' UNION ALL

SELECT id, 'Diriá' FROM zone WHERE name = 'Granada' UNION ALL
SELECT id, 'Diriomo' FROM zone WHERE name = 'Granada' UNION ALL
SELECT id, 'Granada' FROM zone WHERE name = 'Granada' UNION ALL
SELECT id, 'Nandaime' FROM zone WHERE name = 'Granada' UNION ALL

SELECT id, 'El Cuá' FROM zone WHERE name = 'Jinotega' UNION ALL
SELECT id, 'Jinotega' FROM zone WHERE name = 'Jinotega' UNION ALL
SELECT id, 'La Concordia' FROM zone WHERE name = 'Jinotega' UNION ALL
SELECT id, 'San José de Bocay' FROM zone WHERE name = 'Jinotega' UNION ALL
SELECT id, 'San Rafael del Norte' FROM zone WHERE name = 'Jinotega' UNION ALL
SELECT id, 'San Sebastián de Yalí' FROM zone WHERE name = 'Jinotega' UNION ALL
SELECT id, 'Santa María de Pantasma' FROM zone WHERE name = 'Jinotega' UNION ALL
SELECT id, 'Wiwilí de Jinotega' FROM zone WHERE name = 'Jinotega' UNION ALL

SELECT id, 'Achuapa' FROM zone WHERE name = 'León' UNION ALL
SELECT id, 'El Jicaral' FROM zone WHERE name = 'León' UNION ALL
SELECT id, 'El Sauce' FROM zone WHERE name = 'León' UNION ALL
SELECT id, 'La Paz Centro' FROM zone WHERE name = 'León' UNION ALL
SELECT id, 'Larreynaga' FROM zone WHERE name = 'León' UNION ALL
SELECT id, 'León' FROM zone WHERE name = 'León' UNION ALL
SELECT id, 'Nagarote' FROM zone WHERE name = 'León' UNION ALL
SELECT id, 'Quezalguaque' FROM zone WHERE name = 'León' UNION ALL
SELECT id, 'Santa Rosa del Peñón' FROM zone WHERE name = 'León' UNION ALL
SELECT id, 'Telica' FROM zone WHERE name = 'León' UNION ALL

SELECT id, 'Las Sabanas' FROM zone WHERE name = 'Madriz' UNION ALL
SELECT id, 'Palacagüina' FROM zone WHERE name = 'Madriz' UNION ALL
SELECT id, 'San José de Cusmapa' FROM zone WHERE name = 'Madriz' UNION ALL
SELECT id, 'San Juan de Río Coco' FROM zone WHERE name = 'Madriz' UNION ALL
SELECT id, 'San Lucas' FROM zone WHERE name = 'Madriz' UNION ALL
SELECT id, 'Somoto' FROM zone WHERE name = 'Madriz' UNION ALL
SELECT id, 'Telpaneca' FROM zone WHERE name = 'Madriz' UNION ALL
SELECT id, 'Totogalpa' FROM zone WHERE name = 'Madriz' UNION ALL
SELECT id, 'Yalagüina' FROM zone WHERE name = 'Madriz' UNION ALL

SELECT id, 'Ciudad Sandino' FROM zone WHERE name = 'Managua' UNION ALL
SELECT id, 'El Crucero' FROM zone WHERE name = 'Managua' UNION ALL
SELECT id, 'Managua' FROM zone WHERE name = 'Managua' UNION ALL
SELECT id, 'Mateare' FROM zone WHERE name = 'Managua' UNION ALL
SELECT id, 'San Francisco Libre' FROM zone WHERE name = 'Managua' UNION ALL
SELECT id, 'San Rafael del Sur' FROM zone WHERE name = 'Managua' UNION ALL
SELECT id, 'Ticuantepe' FROM zone WHERE name = 'Managua' UNION ALL
SELECT id, 'Tipitapa' FROM zone WHERE name = 'Managua' UNION ALL
SELECT id, 'Villa El Carmen' FROM zone WHERE name = 'Managua' UNION ALL

SELECT id, 'Catarina' FROM zone WHERE name = 'Masaya' UNION ALL
SELECT id, 'La Concepción' FROM zone WHERE name = 'Masaya' UNION ALL
SELECT id, 'Masatepe' FROM zone WHERE name = 'Masaya' UNION ALL
SELECT id, 'Masaya' FROM zone WHERE name = 'Masaya' UNION ALL
SELECT id, 'Nandasmo' FROM zone WHERE name = 'Masaya' UNION ALL
SELECT id, 'Nindirí' FROM zone WHERE name = 'Masaya' UNION ALL
SELECT id, 'Niquinohomo' FROM zone WHERE name = 'Masaya' UNION ALL
SELECT id, 'San Juan de Oriente' FROM zone WHERE name = 'Masaya' UNION ALL
SELECT id, 'Tisma' FROM zone WHERE name = 'Masaya' UNION ALL

SELECT id, 'Ciudad Darío' FROM zone WHERE name = 'Matagalpa' UNION ALL
SELECT id, 'El Tuma - La Dalia' FROM zone WHERE name = 'Matagalpa' UNION ALL
SELECT id, 'Esquipulas' FROM zone WHERE name = 'Matagalpa' UNION ALL
SELECT id, 'Matagalpa' FROM zone WHERE name = 'Matagalpa' UNION ALL
SELECT id, 'Matiguás' FROM zone WHERE name = 'Matagalpa' UNION ALL
SELECT id, 'Muy Muy' FROM zone WHERE name = 'Matagalpa' UNION ALL
SELECT id, 'Rancho Grande' FROM zone WHERE name = 'Matagalpa' UNION ALL
SELECT id, 'Río Blanco' FROM zone WHERE name = 'Matagalpa' UNION ALL
SELECT id, 'San Dionisio' FROM zone WHERE name = 'Matagalpa' UNION ALL
SELECT id, 'San Isidro' FROM zone WHERE name = 'Matagalpa' UNION ALL
SELECT id, 'San Ramón' FROM zone WHERE name = 'Matagalpa' UNION ALL
SELECT id, 'Sébaco' FROM zone WHERE name = 'Matagalpa' UNION ALL
SELECT id, 'Terrabona' FROM zone WHERE name = 'Matagalpa' UNION ALL

SELECT id, 'Ciudad Antigua' FROM zone WHERE name = 'Nueva Segovia' UNION ALL
SELECT id, 'Dipilto' FROM zone WHERE name = 'Nueva Segovia' UNION ALL
SELECT id, 'El Jícaro' FROM zone WHERE name = 'Nueva Segovia' UNION ALL
SELECT id, 'Jalapa' FROM zone WHERE name = 'Nueva Segovia' UNION ALL
SELECT id, 'Macuelizo' FROM zone WHERE name = 'Nueva Segovia' UNION ALL
SELECT id, 'Mozonte' FROM zone WHERE name = 'Nueva Segovia' UNION ALL
SELECT id, 'Murra' FROM zone WHERE name = 'Nueva Segovia' UNION ALL
SELECT id, 'Ocotal' FROM zone WHERE name = 'Nueva Segovia' UNION ALL
SELECT id, 'Quilalí' FROM zone WHERE name = 'Nueva Segovia' UNION ALL
SELECT id, 'San Fernando' FROM zone WHERE name = 'Nueva Segovia' UNION ALL
SELECT id, 'Santa María' FROM zone WHERE name = 'Nueva Segovia' UNION ALL
SELECT id, 'Wiwilí de Nueva Segovia' FROM zone WHERE name = 'Nueva Segovia' UNION ALL

SELECT id, 'El Almendro' FROM zone WHERE name = 'Río San Juan' UNION ALL
SELECT id, 'El Castillo' FROM zone WHERE name = 'Río San Juan' UNION ALL
SELECT id, 'Morrito' FROM zone WHERE name = 'Río San Juan' UNION ALL
SELECT id, 'San Carlos' FROM zone WHERE name = 'Río San Juan' UNION ALL
SELECT id, 'San Juan del Norte' FROM zone WHERE name = 'Río San Juan' UNION ALL
SELECT id, 'San Miguelito' FROM zone WHERE name = 'Río San Juan' UNION ALL

SELECT id, 'Altagracia' FROM zone WHERE name = 'Rivas' UNION ALL
SELECT id, 'Belén' FROM zone WHERE name = 'Rivas' UNION ALL
SELECT id, 'Buenos Aires' FROM zone WHERE name = 'Rivas' UNION ALL
SELECT id, 'Cárdenas' FROM zone WHERE name = 'Rivas' UNION ALL
SELECT id, 'Moyogalpa' FROM zone WHERE name = 'Rivas' UNION ALL
SELECT id, 'Potosí' FROM zone WHERE name = 'Rivas' UNION ALL
SELECT id, 'Rivas' FROM zone WHERE name = 'Rivas' UNION ALL
SELECT id, 'San Jorge' FROM zone WHERE name = 'Rivas' UNION ALL
SELECT id, 'San Juan del Sur' FROM zone WHERE name = 'Rivas' UNION ALL
SELECT id, 'Tola' FROM zone WHERE name = 'Rivas' UNION ALL

SELECT id, 'Bonanza' FROM zone WHERE name = 'Atlántico Norte' UNION ALL
SELECT id, 'Mulukukú' FROM zone WHERE name = 'Atlántico Norte' UNION ALL
SELECT id, 'Prinzapolka' FROM zone WHERE name = 'Atlántico Norte' UNION ALL
SELECT id, 'Puerto Cabezas' FROM zone WHERE name = 'Atlántico Norte' UNION ALL
SELECT id, 'Rosita' FROM zone WHERE name = 'Atlántico Norte' UNION ALL
SELECT id, 'Siuna' FROM zone WHERE name = 'Atlántico Norte' UNION ALL
SELECT id, 'Waslala' FROM zone WHERE name = 'Atlántico Norte' UNION ALL
SELECT id, 'Waspán' FROM zone WHERE name = 'Atlántico Norte' UNION ALL

SELECT id, 'Bluefields' FROM zone WHERE name = 'Atlántico Sur' UNION ALL
SELECT id, 'Corn Island' FROM zone WHERE name = 'Atlántico Sur' UNION ALL
SELECT id, 'Desembocadura de Río Grande' FROM zone WHERE name = 'Atlántico Sur' UNION ALL
SELECT id, 'El Ayote' FROM zone WHERE name = 'Atlántico Sur' UNION ALL
SELECT id, 'El Rama' FROM zone WHERE name = 'Atlántico Sur' UNION ALL
SELECT id, 'El Tortuguero' FROM zone WHERE name = 'Atlántico Sur' UNION ALL
SELECT id, 'Kukra Hill' FROM zone WHERE name = 'Atlántico Sur' UNION ALL
SELECT id, 'La Cruz de Río Grande' FROM zone WHERE name = 'Atlántico Sur' UNION ALL
SELECT id, 'Laguna de Perlas' FROM zone WHERE name = 'Atlántico Sur' UNION ALL
SELECT id, 'Muelle de los Bueyes' FROM zone WHERE name = 'Atlántico Sur' UNION ALL
SELECT id, 'Nueva Guinea' FROM zone WHERE name = 'Atlántico Sur' UNION ALL
SELECT id, 'Paiwas' FROM zone WHERE name = 'Atlántico Sur';