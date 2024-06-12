from neo4j import GraphDatabase
from pymongo import MongoClient

try:
    # MongoDB connection
    mongo_client = MongoClient("mongodb://localhost:27017/")
    db = mongo_client["proyek"]
    print("Connected to MongoDB database")

    # Neo4j connection
    URI = "bolt://localhost:7687"
    AUTH = ("neo4j", "1234567890")

    # Connect to Neo4j
    neo4j_conn = GraphDatabase.driver(uri=URI, auth=AUTH)
    print("Connected to Neo4j database")

    with neo4j_conn.session() as session:
        session.run("RETURN 1")
    print("Verified connectivity to Neo4j database")

except Exception as e:
    print(f"Error connecting to databases: {e}")
