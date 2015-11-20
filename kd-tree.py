#! /usr/bin/python
import fileinput
import csv

points = []
nearest = []

#This represents our node.  Each node has a coordinate and a left and right node
class Node:
    def __init__(self, id, coords, left, right):
        self.id = id
        self.coords = coords
        self.left = left
        self.right = right

def prep(file):
    global points
    global nearest

    with open(file, 'rb') as csvfile:
        rows = csv.reader(csvfile, delimiter=',', quotechar='"')
        for row in rows:
            points.append([row[1],row[2]])

    root = generate(points)

    points.sort(cmp=lambda x,y: float_compare(x[0], y[0]))

    for point in points:
        nearest_3 = find_nearest(root, point[1:3])
        print str(point[0]) + " " + str(nearest_3[1][1]) + ","  + str(nearest_3[2][1]) + ","  + str(nearest_3[3][1])
        nearest = []


def distance(node, target):
    if node == None or target == None:
        return None
    else:
      c = (node.coords[0] - target[0])
      d = (node.coords[1] - target[1])
      return c * c + d * d


def check_nearest(nearest, node, target):
    d = distance(node, target)

    if len(nearest) < 4 or d < nearest[-1][0]:
        if len(nearest) >= 4:
            nearest.pop()
        nearest.append([d, node.id])
        nearest.sort(cmp=lambda x,y: float_compare(x[0], y[0]))

    return nearest


def find_nearest(node, target, depth=0):
    global nearest

    axis = depth % 2

    if node.left == None and node.right == None:
        nearest = check_nearest(nearest, node, target)
        return

    if node.right == None or (node.left and target[axis] <= node.coords[axis]):
        nearer = node.left
        further = node.right
    else:
        nearer = node.right
        further = node.left

    find_nearest(nearer, target, depth+1)

    if further:
        if len(nearest) < 4 or (target[axis] - node.coords[axis])**2 < nearest[-1][0]:
            find_nearest(further, target, depth+1)

    nearest = check_nearest(nearest, node, target)
    return nearest

#Generate our KDTree.  Depth starts at 0 and gets incremented as we recurse
def generate(points, tree_depth = 0):
    if points == []:
        return

    #This is either 1 or 2 since we are using a 2 dimensional space
    axis = tree_depth % 2 + 1;

    #Sort the points by their coordinates
    points.sort(cmp=lambda x, y: float_compare(x[axis], y[axis]))

    #Pick the middle point of the tree to start as the root.
    median = len(points) / 2

    #Set this node to the first value
    node = Node(points[median][0], points[median][1:3], None, None)

    #Generate the left side of the tree.
    node.left = generate(points[0:median], tree_depth + 1)

    #Generate the right side of the tree.
    node.right = generate(points[median+1:], tree_depth + 1 )

    return node

#Our comparator
def float_compare(x, y):
    if x > y:
        return 1
    elif x==y:
        return 0
    else:
        return -1


if __name__ == "__main__":
    import sys
    if len(sys.argv) < 2:
        sys.exit()
    prep(sys.argv[1])
