from conn import neo4j_conn, db

def create_nodes_and_relationships():
    with neo4j_conn.session() as session:
        # Pasien
        for pasien_doc in db["pasien"].find():
            session.run(
                """
                MERGE (p:Pasien {name: $name, patient_id: $patient_id})
                """,
                name=pasien_doc["nama"],
                patient_id=pasien_doc["document_id"]
            )

        # Dokter
        for dokter_doc in db["dokter"].find():
            session.run(
                """
                MERGE (d:Dokter {name: $name, doc_id: $doc_id})
                """,
                name=dokter_doc["nama"],    
                doc_id=dokter_doc["document_id"]
            )

        # IGD
        for igd_doc in db["igd"].find():
            session.run(
                """
                MERGE (i:IGD {document_id: $document_id, type: $type})
                """,
                document_id=igd_doc["document_id"],
                type=igd_doc["type"]
            )
            session.run(
                """
                MATCH (p:Pasien {name: $patient_name}), (i:IGD {document_id: $document_id})
                MERGE (p)-[:MELAKUKAN]->(i)
                """,
                patient_name=igd_doc["nama_pasien"],
                document_id=igd_doc["document_id"]
            )
            session.run(
                """
                MATCH (d:Dokter {name: $doctor_name}), (i:IGD {document_id: $document_id})
                MERGE (d)-[:MENANGANI]->(i)
                """,
                doctor_name=igd_doc["doctor_in_charge"],
                document_id=igd_doc["document_id"]
            )

        # Rawat Inap
        for ri_doc in db["ri"].find():
            session.run(
                """
                MERGE (ri:RawatInap {document_id: $document_id, type: $type})
                """,
                document_id=ri_doc["document_id"],
                type=ri_doc["type"]
            )
            session.run(
                """
                MATCH (p:Pasien {name: $patient_name}), (ri:RawatInap {document_id: $document_id})
                MERGE (p)-[:MELAKUKAN]->(ri)
                """,
                patient_name=ri_doc["nama_pasien"],
                document_id=ri_doc["document_id"]
            )
            session.run(
                """
                MATCH (d:Dokter {name: $doctor_name}), (ri:RawatInap {document_id: $document_id})
                MERGE (d)-[:MENANGANI]->(ri)
                """,
                doctor_name=ri_doc["doctor_in_charge"],
                document_id=ri_doc["document_id"]
            )

        # Rawat Jalan
        for rj_doc in db["rj"].find():
            session.run(
                """
                MERGE (rj:RawatJalan {document_id: $document_id, type: $type})
                """,
                document_id=rj_doc["document_id"],
                type=rj_doc["type"]
            )
            session.run(
                """
                MATCH (p:Pasien {name: $patient_name}), (rj:RawatJalan {document_id: $document_id})
                MERGE (p)-[:MELAKUKAN]->(rj)
                """,
                patient_name=rj_doc["nama_pasien"],
                document_id=rj_doc["document_id"]
            )
            session.run(
                """
                MATCH (d:Dokter {name: $doctor_name}), (rj:RawatJalan {document_id: $document_id})
                MERGE (d)-[:MENANGANI]->(rj)
                """,
                doctor_name=rj_doc["doctor_in_charge"],
                document_id=rj_doc["document_id"]
            )
        
      # Relation for every appointment
        for pasien in db["pasien"].find():
            patient_name = pasien["nama"]

            # Check in IGD
            igd_doc = db["igd"].find_one({"nama_pasien": patient_name})
            if igd_doc:
                igd_id = igd_doc["document_id"]
                igd_doctor = igd_doc["doctor_in_charge"]
                session.run(
                    """
                    MATCH (p:Pasien {name: $patient_name}), (i:IGD {document_id: $igd_id})
                    MERGE (p)-[:MELAKUKAN]->(i)
                    """,
                    patient_name=patient_name,
                    igd_id=igd_id
                )
                session.run(
                    """
                    MATCH (d:Dokter {name: $doctor_name}), (i:IGD {document_id: $igd_id})
                    MERGE (d)-[:MENANGANI]->(i)
                    """,
                    doctor_name=igd_doctor,
                    igd_id=igd_id
                )

                # Check in RawatInap
                ri_doc = db["ri"].find_one({"nama_pasien": patient_name})
                if ri_doc:
                    ri_id = ri_doc["document_id"]
                    ri_doctor = ri_doc["doctor_in_charge"]
                    session.run(
                        """
                        MATCH (i:IGD {document_id: $igd_id}), (ri:RawatInap {document_id: $ri_id})
                        MERGE (i)-[:BERLANJUT_KE]->(ri)
                        """,
                        igd_id=igd_id,
                        ri_id=ri_id
                    )
                    session.run(
                        """
                        MATCH (d:Dokter {name: $doctor_name}), (ri:RawatInap {document_id: $ri_id})
                        MERGE (d)-[:MENANGANI]->(ri)
                        """,
                        doctor_name=ri_doctor,
                        ri_id=ri_id
                    )

                # Check in RawatJalan
                rj_doc = db["rj"].find_one({"nama_pasien": patient_name})
                if rj_doc:
                    rj_id = rj_doc["document_id"]
                    rj_doctor = rj_doc["doctor_in_charge"]
                    session.run(
                        """
                        MATCH (i:IGD {document_id: $igd_id}), (rj:RawatJalan {document_id: $rj_id})
                        MERGE (i)-[:BERLANJUT_KE]->(rj)
                        """,
                        igd_id=igd_id,
                        rj_id=rj_id
                    )
                    session.run(
                        """
                        MATCH (d:Dokter {name: $doctor_name}), (rj:RawatJalan {document_id: $rj_id})
                        MERGE (d)-[:MENANGANI]->(rj)
                        """,
                        doctor_name=rj_doctor,
                        rj_id=rj_id
                    )
    print('All nodes and relationships created successfully')

create_nodes_and_relationships()