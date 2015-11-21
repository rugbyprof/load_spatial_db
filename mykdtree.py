import sys
import csv
from scipy import spatial
import numpy as np
import time
import random
from haversine import haversine

class RoadSegment:
    def __init__(self,id,lat1,lng1,lat2,lng2,code1,code2,name,state,contig,distance,avg_bearing,start_bearing,end_bearing):
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
        self.avg_bearing = avg_bearing
        self.start_bearing = start_bearing
        self.end_bearing = end_bearing


    def __str__(self):
        return "[%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s]" % (self.id ,self.lat1 ,self.lng1 ,self.lat2 ,self.lng2 ,self.code1 ,self.code2 ,self.name ,self.state ,self.contig ,self.distance ,self.avg_bearing ,self.start_bearing ,self.end_bearing)


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

    def distance(node, target):
        if node == None or target == None:
            return None
        else:
          c = (node.coords[0] - target[0])
          d = (node.coords[1] - target[1])
          return c * c + d * d


if __name__ == '__main__':
    start_time = time.time()
    print "Loading tree ..."
    points = []
    segments = []
    with open(sys.argv[1], 'rb') as csvfile:
        rows = csv.reader(csvfile, delimiter=',', quotechar='"')
        for row in rows:
            segments.append(RoadSegment(row[0],row[1],row[2],row[3],row[4],row[5],row[6],row[7],row[8],row[9],row[10],row[11],row[12],row[13]))
            points.append([float(row[1]),float(row[2])])

    print("Tree loaded in %f seconds." % (time.time() - start_time))
    tree = spatial.KDTree(points)
    for x in range(10):
        p = random.randint(0, len(points))
        pts = np.array(points[p])
        distances,neighbors =  tree.query(pts,5)

        print segments[p]
        for n in neighbors:
            print n,'=>',segments[n]

        # for i in range(len(d)):
        #     if r[i] != p:
        #         print haversine((points[p] ),(points[r[i]])) , d[1] , r[i] , points[r[i]] , pointData[r[i]][0] , pointData[r[i]][1], pointData[r[i]][2] , pointData[r[i]][3]

    start_time = time.time()
    print("Queried Tree in %f seconds." % (time.time() - start_time))
