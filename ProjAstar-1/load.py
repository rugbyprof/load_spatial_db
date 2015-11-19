import csv
import json
import random

"""
https://www.python.org/doc/essays/graphs/
"""

nodes = []
edges = []
geometry = {}
graph = dict()

def find_path(graph, start, end, path=[]):
        path = path + [start]
        if start == end:
            return path
        if not graph.has_key(start):
            return None
        for node in graph[start]:
            if node not in path:
                newpath = find_path(graph, node, end, path)
                if newpath: return newpath
        return None

with open('nodes.csv', 'rb') as csvfile:
    rows = csv.reader(csvfile, delimiter=',', quotechar='"')
    for row in rows:
        nodes.append(row)

with open('edges.csv', 'rb') as csvfile:
    rows = csv.reader(csvfile, delimiter=',', quotechar='"')
    for row in rows:
        edges.append(row)

f = open('nodegeometry.json', 'r')

for line in f:
    line = json.loads(line)
    #print line['id']
    geometry[line['id']] = line['geometry']


geo =  json.loads(geometry[str(203980)])

for e in edges:
    A,B = e
    if A in graph:
        graph[A].append(B)
    else:
        graph[A] = [B]



start = random.choice(graph.keys())
end = random.choice(graph.keys())

print find_path(graph, start, end)
