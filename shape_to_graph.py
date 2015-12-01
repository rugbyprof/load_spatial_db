import networkx
import haversine

graph = networkx.read_shp("shape/tl_2013_48_prisecroads.shp")

# f = open('road_nodes.txt', 'w')
#     f.write(line)
#print len(graph.nodes())


f = open('texas_graph_nodes.txt', 'w')

node_map = {}
i = 0
for node in graph.nodes():
    lat = round(node[1],7)
    lng = round(node[0],7)
    line = str(i)+","+str(lat) +","+ str(lng)+"\n"

    node_map[i] = (lat,lng)
    f.write(str(i)+","+str(lat)+","+str(lng)+"\n")
    i += 1

f.close()

f = open('texas_graph_edges.txt', 'w')

for edge in graph.edges():
    lat1 = round(edge[0][1],7)
    lng1 = round(edge[0][0],7)
    lat2 = round(edge[1][1],7)
    lng2 = round(edge[1][0],7)
    f.write(str(lat1)+","+str(lng1)+","+str(lat2)+","+str(lng2)+"\n")

    min1 = 999999;
    ind1 = 0;
    min2 = 999999;
    ind2 = 0;
    for key, value in node_map.iteritems():
        d1 =  haversine.haversine(value, (lat1,lng1),True)
        if d1 < min1:
            min1 = d1
            ind1 = key
        d2 =  haversine.haversine(value, (lat2,lng2),True)
        if d2 < min2:
            min2 = d2
            ind2 = key
    print min1," ",ind1," ",min2," ",ind2

f.close()

# for key, value in records.iteritems():
#     if value is None:
#         records[key] = 0
