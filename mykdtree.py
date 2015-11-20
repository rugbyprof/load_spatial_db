import sys
import csv

class RoadSegment:
    def __init__(self,id,lat1,lng1,lat2,lng2,code1,code2,name,state,contig,distance,bearing):
        self.id = id
        self.lat1 = lat1
        self.lng1 = lng1
        self.lat2 = lat2
        self.lng2 = lng2
        self.code1 = code1
        self.code2 = code2
        self.name = name
        self.state = state
        self.contig = contig
        self.distance = distance
        self.bearing = bearing

class Point:
    def __init__(self,id,lat,lng):
        self.id = id
        self.lat = lat
        self.lng = lng

class Node:
    def __init__(self, point):
        self.discriminator = None
        self.left = None
        self.right= None
        self.point = point

class Tree:
    def __init__(self):
        self.root = None

    def getRoot(self):
        return self.root

    def add(self, point):
        if(self.root == None):
            self.root = Node(point)
        else:
            self._add(point, self.root)

    def _add(self, point, node):
        if(point.lat < node.point.lat):
            if(node.left is not None):
                self._add(point, node.left)
            else:
                node.left = Node(point)
        else:
            if(node.right is not None):
                self._add(point, node.right)
            else:
                node.right= Node(point)

    def find(self, lat):
        if(self.root is not None):
            return self._find(lat, self.root)
        else:
            return None

    def _find(self, lat, node):
        if(lat == node.point.lat):
            return node
        elif(lat < node.point.lat and node.left != None):
            self._find(point, node.left)
        elif(lat > node.point.lat and node.right!= None):
            self._find(point, node.right)

    def deleteTree(self):
        # garbage collector will do this for us.
        self.root = None

    def printTree(self):
        if(self.root is not None):
            self._printTree(self.root)

    def _printTree(self, node):
        if(node != None):
            self._printTree(node.left)
            print str(node.point.lat) + ' ' + str(node.point.lat)
            self._printTree(node.right)

    def distance(node, target):
        if node == None or target == None:
            return None
        else:
          c = (node.coords[0] - target[0])
          d = (node.coords[1] - target[1])
          return c * c + d * d

if __name__ == '__main__':

    points = []
    with open(sys.argv[1], 'rb') as csvfile:
        rows = csv.reader(csvfile, delimiter=',', quotechar='"')
        for row in rows:
            #segment = RoadSegment(row[0],row[1],row[2],row[3],row[4],row[5],row[6],row[7],row[8],row[9],row[10],row[11])
            point = Point(row[0],row[1],row[2])
            points.append(point)
    kd = Tree()

    for p in points:
        kd.add(p)
    kd.printTree()
