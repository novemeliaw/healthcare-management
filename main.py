from pymongo import MongoClient
from py2neo import Graph, Node, Relationship

# Connect to MongoDB
mongo_client = MongoClient("mongodb://localhost:27017/")
db = mongo_client["your_database_name"]
collection = db["your_collection_name"]

# Connect to Neo4j
neo4j_graph = Graph("bolt://localhost:7687", auth=("neo4j", "your_password"))

def create_node_from_document(doc):
    if doc.get("document_id", "").startswith("D"):
        node = Node("Doctor", name=doc["nama"], doc_id=doc["doc_id"])
        neo4j_graph.create(node)

    elif doc.get("document_id", "").startswith("IGD"):
        node = Node("IGD", document_id=doc["document_id"], type=doc["type"],)
        neo4j_graph.create(node)

    elif doc.get("document_id", "").startswith("RI"):
        node = Node("Rawat Inap",
                    document_id=doc["document_id"],
                    type=doc["type"])
        neo4j_graph.create(node)

    elif doc.get("document_id", "").startswith("RJ"):
        node = Node("Rawat Jalan",
                    document_id=doc["document_id"],
                    type=doc["type"])
        neo4j_graph.create(node)

    elif doc.get("document_id", "").startswith("P"):
        node = Node("Pasien",
                    document_id=doc["document_id"],
                    nama=doc["nama"])
        neo4j_graph.create(node)

documents = collection.find()
for document in documents:
    create_node_from_document(document)
